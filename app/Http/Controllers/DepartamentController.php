<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApiResponse;
use App\Models\Departament;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartamentController extends Controller
{
    public function createorUpdate(Request $request)
    {
        try {

            $departament = Departament::updateOrCreate(
                ['id' => $request->id],
                [
                    'name' => $request->name,
                    'classification_code' => $request->classification_code,
                    'departament_id' => $request->departament_id,
                    'abbreviation' => $request->abbreviation,
                    'authorized' => $request->authorized ?? false,
                ]
            );

            if ($departament->wasRecentlyCreated) {
                $message = "Se creó correctamente";
            } elseif ($departament->wasChanged()) {
                $message = "Se actualizó correctamente";
            } else {
                $message = "No hubo cambios";
            }

            return ApiResponse::success($departament, $message);
        } catch (Exception $e) {
            Log::info("departamentos save: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
    private function addLevel($departments, $level = 0)
    {
        return $departments->map(function ($dept) use ($level) {
            $dept['level'] = $level;

            if (!empty($dept['children_recursive'])) {
                $dept['children_recursive'] = $this->addLevel(
                    collect($dept['children_recursive']),
                    $level + 1
                );
            }

            return $dept;
        });
    }
    public function index()
    {
        try {
            $departments = Departament::whereNull('departament_id')
                ->with([
                    'childrenRecursive' => function ($query) {
                        $query->where('active', true)
                            ->with(['director' => function ($q) {
                                $q->where('active', true)
                                    ->where('role', 'director')
                                    ->select('id', 'fullName', 'departament_id');
                            }]);
                    },
                    'director' => function ($q) {
                        $q->where('active', true)
                            ->where('role', 'director')
                            ->select('id', 'fullName', 'departament_id');
                    }
                ])
                ->where("active", true)
                ->get();

            // Función recursiva para formatear
            $formatDepartment = function ($department) use (&$formatDepartment) {
                $deptArray = $department->toArray();

                // Agregar responsable del nivel actual
                $deptArray['responsible'] = $department->director ? $department->director->fullName : null;

                // Procesar hijos recursivamente usando los modelos, no los arrays
                if ($department->childrenRecursive && $department->childrenRecursive->count() > 0) {
                    $deptArray['children_recursive'] = $department->childrenRecursive
                        ->map(function ($child) use ($formatDepartment) {
                            return $formatDepartment($child);
                        })
                        ->toArray();
                } else {
                    $deptArray['children_recursive'] = [];
                }

                return $deptArray;
            };

            // Aplicar formato a todos los departamentos raíz
            $formattedDepartments = $departments->map($formatDepartment);

            // Aplicar niveles
            $formattedDepartments = $this->addLevel(collect($formattedDepartments));

            return ApiResponse::success($formattedDepartments, 'Departamentos obtenidos correctamente');
        } catch (Exception $e) {
            Log::error("departamentos index: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }

    private function deactivateTree(Departament $departament)
    {
        // Primero desactivar hijos
        foreach ($departament->children as $child) {
            $this->deactivateTree($child);
        }

        // Luego el actual
        $departament->update([
            'active' => false
        ]);
    }
    public function destroy(Request $request)
    {
        try {

            $departament = Departament::with('childrenRecursive')
                ->findOrFail($request->id);

            $this->deactivateTree($departament);

            return ApiResponse::success(null, 'Se dio de baja la dirección con sus subdirecciones');
        } catch (\Exception $e) {
            Log::info("departamentos destroy: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
    public function authorized(Request $request)
    {
        try {
            $departament = Departament::find($request->id);

            if (!$departament) {
                return ApiResponse::error('Usuario no encontrado', 404);
            }

            $departament->update(['authorized' => DB::raw('NOT authorized')]);;
            $departament->refresh(); // 🔥 Esto es lo que falta
            return ApiResponse::success(
                null,
                $departament->authorized
                    ? 'Autorización otorgada correctamente'
                    : 'Se elimino la autorización correctamente'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Error en la acción', 500);
        }
    }
    public function getAbbreviationPath($id)
    {
        try {
            $departament = Departament::find($id);

            if (!$departament) {
                return ApiResponse::error('Departamento no encontrado', 404);
            }

            $path = $this->buildAbbreviationPath($departament);

            return  $path;
        } catch (Exception $e) {
            Log::error("departamentos getAbbreviationPath: " . $e->getMessage());
            return $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sube recursivamente acumulando abreviaturas en un array, luego arma
    // la cadena con el orden: raíz + nodo enviado + intermedios sin raíz.
    //
    // Internamente collectAncestors devuelve [TI, DAO, CM] (de hijo a raíz).
    // Después:
    //   - raíz    = último elemento  → CM
    //   - resto   = todos sin raíz   → [TI, DAO]
    //   - resultado = CM + "-" + TI + "-" + DAO  →  CM-TI-DAO
    // ─────────────────────────────────────────────────────────────────────────
    private function buildAbbreviationPath(Departament $departament): string
    {
        // Si el nodo enviado ES la raíz, solo devuelve su abreviatura
        if (is_null($departament->departament_id)) {
            return $departament->abbreviation;
        }

        // Recolecta todos los ancestros de hijo → raíz: [TI, DAO, CM]
        $chain = $this->collectAncestors($departament);

        // La raíz es el último elemento
        $root = end($chain);

        // El resto son todos menos la raíz: [TI, DAO]
        $withoutRoot = array_slice($chain, 0, count($chain) - 1);

        // Arma: CM - TI - DAO
        $abbreviations = array_merge(
            [$root->abbreviation],
            array_map(fn($d) => $d->abbreviation, $withoutRoot)
        );

        return implode('-', $abbreviations);
    }

    // Sube de hijo en hijo hasta la raíz y devuelve el array en ese orden.
    // Ejemplo: TI → [TI, DAO, CM]
    private function collectAncestors(Departament $departament): array
    {
        $chain = [$departament];

        $current = $departament;

        while (!is_null($current->departament_id)) {
            $parent = Departament::find($current->departament_id);

            // Seguridad: si el padre no existe se corta la cadena
            if (!$parent) {
                break;
            }

            $chain[] = $parent;
            $current  = $parent;
        }

        return $chain;
    }
}
