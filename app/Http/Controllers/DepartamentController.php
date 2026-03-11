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
            // Mapear a una nueva estructura
            $formattedDepartments = $departments->map(function ($department) {
                $deptArray = $department->toArray();
                // Reemplazar director por solo el nombre
                $deptArray['responsible'] = $department->director ? $department->director->fullName : null;

                // Procesar hijos recursivamente
                if (isset($deptArray['children_recursive']) && is_array($deptArray['children_recursive'])) {
                    $deptArray['children_recursive'] = collect($deptArray['children_recursive'])->map(function ($child) use ($department) {
                        // Buscar el child real con la relación cargada
                        $childModel = $department->childrenRecursive->firstWhere('id', $child['id']);
                        if ($childModel && $childModel->director) {
                            $child['responsible'] = $childModel->director->fullName;
                        } else {
                            $child['responsible'] = null;
                        }
                        return $child;
                    })->toArray();
                }

                return $deptArray;
            });
            $formattedDepartments = $this->addLevel($formattedDepartments);

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
}
