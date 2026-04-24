<?php

namespace App\Http\Controllers;

use App\Models\ApiResponse;
use App\Models\Departament;
use App\Models\Proccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProccessController extends Controller
{
    public function createorUpdate(Request $request)
    {
        try {

            $data = [
                'name' => $request->name,
                'departament_id' => $request->departament_id,
                'classification_code' => $request->classification_code,
                'description' => $request->description,
                'at' => $request->at,
                'ac' => $request->ac,
                'proccess_id' => $request->proccess_id,

            ];

            // Verificar si tiene id Y que sea mayor que 0
            if ($request->filled('id') && $request->id > 0) {
                $proccess = Proccess::find($request->id);
                if ($proccess) {
                    $proccess->update($data);
                    $message = "Se actualizó correctamente";
                } else {
                    return ApiResponse::error('Registro no encontrado', 404);
                }
            } else {
                $proccess = Proccess::create($data);
                $message = "Se creó correctamente";
            }

            return ApiResponse::success($proccess, $message);
        } catch (Exception $e) {
            Log::info("proccess save: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
    private function addLevel($items, $level = 0)
    {
        return $items->map(function ($item) use ($level) {
            $children = $item->childrenRecursive ?? collect();

            return [
                'id'                 => $item->id,
                'classification_code'               => $item->classification_code,
                'name'               => $item->name,
                'at'               => $item->at,

                'ac'               => $item->ac,
                'total'               => $item->total,
                'description'               => $item->description,
                'active'               => $item->active,

                'departament_id'     => $item->departament_id,
                'level'              => $level,
                'selectable'         => $children->isEmpty(), // ← se calcula aquí
                'children_recursive' => $this->addLevel($children, $level + 1),
            ];
        })->values();
    }
    public function index(int $id)
    {
        try {
            $proccess = Proccess::whereNull('proccess_id')
                ->with([
                    'childrenRecursive' => function ($query) {
                        $query->where('active', true);
                    }
                ])
                ->where('departament_id', $id)

                ->get();
            // Mapear a una nueva estructura

            $formattedDepartments = $this->addLevel($proccess);

            return ApiResponse::success($formattedDepartments, 'Procesos obtenidos correctamente');
        } catch (Exception $e) {
            Log::error("proccess index: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }

    private function getDepartmentChildrenIds($departmentId)
    {
        $ids = [$departmentId];

        // Obtener todos los departamentos hijos directos
        $children = Departament::where('departament_id', $departmentId)
            ->where('active', true)
            ->pluck('id')
            ->toArray();

        foreach ($children as $childId) {
            // Recursivamente obtener los hijos de cada hijo
            $ids = array_merge($ids, $this->getDepartmentChildrenIds($childId));
        }

        return $ids;
    }
    private function buildDepartmentTree($departments, $parentId = null)
    {
        return $departments
            ->where('departament_id', $parentId)
            ->map(function ($dept) use ($departments) {
                $children = $this->buildDepartmentTree($departments, $dept->id);
                $processes = Proccess::whereNull('proccess_id')
                    ->where('departament_id', $dept->id)
                    ->where('active', true)
                    ->with(['childrenRecursive' => function ($q) {
                        $q->where('active', true);
                    }])
                    ->get();

                $processNodes = $this->addLevel($processes);

                // hijos = subdepartamentos + procesos del departamento
                $allChildren = $children->concat($processNodes);

                return [
                    'id'                 => 'dept_' . $dept->id,
                    'name'               => $dept->name,
                    'selectable'         => false,
                    'children_recursive' => $allChildren->values(),
                ];
            })->values();
    }

    public function processByUser()
    {
        try {
            if (Auth::user()->role != "administrador") {
                $departmentIds = $this->getDepartmentChildrenIds(Auth::user()->departament_id);
                $departments = Departament::whereIn('id', $departmentIds)
                    ->where('active', true)
                    ->get();

                // Usar directamente el departamento del usuario como raíz
                $tree = $this->buildDepartmentTree(
                    $departments,
                    $departments->first()?->departament_id  // padre real
                );

                // Si sigue vacío, el usuario ES el nodo raíz — construir desde su propio depto
                if ($tree->isEmpty()) {
                    $tree = $this->buildDepartmentTree(
                        $departments,
                        null  // tratarlo como raíz
                    );
                }
            } else {
                $departments = Departament::where('active', true)->get();
                $tree = $this->buildDepartmentTree($departments, null);
            }

            return ApiResponse::success($tree, 'Procesos obtenidos correctamente');
        } catch (Exception $e) {
            Log::error("proccess processByUser: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
    // ```

    // El árbol quedaría así:
    // ```
    // Oficialía Mayor          (selectable: false)
    //   └─ Dirección CETIC     (selectable: false)
    //        ├─ t1             (selectable: true)
    //        └─ t2             (selectable: true)
    //   └─ Contraloría         (selectable: false)
    //        └─ t1             (selectable: true)
    private function deactivateTree(Proccess $departament)
    {
        // Primero desactivar hijos
        foreach ($departament->children as $child) {
            $this->deactivateTree($child);
        }

        // Luego el actual
        $departament->update([
            'active' => !$departament->active
        ]);
    }
    public function destroy(Request $request)
    {
        try {
            // return $request;
            $departament = Proccess::with('childrenRecursive')
                ->findOrFail($request->id);

            $this->deactivateTree($departament);

            return ApiResponse::success(null, $departament->active ? "Se dio de alta el trámite con sus subtramites" : "Se dio de baja el trámite con sus subtramites");
        } catch (\Exception $e) {
            Log::info("proccesos destroy: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
}
