<?php

namespace App\Http\Controllers;

use App\Models\ApiResponse;
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
                'proccess_id'=> $request->proccess_id,

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
    private function addLevel($departments, $level = 0)
    {
        return $departments->map(function ($dept) use ($level) {

            $dept->level = $level;

            if ($dept->childrenRecursive && $dept->childrenRecursive->isNotEmpty()) {
                $dept->childrenRecursive = $this->addLevel(
                    $dept->childrenRecursive,
                    $level + 1
                );
            }

            return $dept;
        });
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
    public function processByUser()
    {
        try {
            $proccess = Proccess::whereNull('proccess_id')
                ->with([
                    'childrenRecursive' => function ($query) {
                        $query->where('active', true);
                    }
                ]);
            if (Auth::user()->role != "administrador") {
                # code...
                $proccess = $proccess->where('departament_id', Auth::user()->departament_id);
            }

            $proccess = $proccess->get();
            // Mapear a una nueva estructura

            $formattedDepartments = $this->addLevel($proccess);

            return ApiResponse::success($formattedDepartments, 'Procesos obtenidos correctamente');
        } catch (Exception $e) {
            Log::error("proccess index: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }

    private function deactivateTree(Proccess $departament)
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

            $departament = Proccess::with('childrenRecursive')
                ->findOrFail($request->id);

            $this->deactivateTree($departament);

            return ApiResponse::success(null, 'Se dio de baja el tramite con sus subtramites');
        } catch (\Exception $e) {
            Log::info("proccesos destroy: " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
}
