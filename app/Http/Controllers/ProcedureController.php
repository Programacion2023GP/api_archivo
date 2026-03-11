<?php

namespace App\Http\Controllers;

use App\Models\ApiResponse;
use App\Models\Procedure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProcedureController extends Controller
{
    public function createOrUpdate(Request $request)
    {
        try {
            $items = $request->all(); // arreglo que envía React
            $procedures = [];

            foreach ($items as $item) {
                $data = [
                    'boxes' => $item['boxes'] ?? null,
                    'fileNumber' => $item['fileNumber'] ?? null,
                    'archiveCode' => $item['archiveCode'] ?? null,
                    'process_id' => $item['process_id'] ?? null,
                    'user_id' => Auth::user()->id,
                    'departament_id' => Auth::user()->departament_id,
                    'description' => $item['description'] ?? null,
                    'digital' => $item['digital'] ?? false,
                    'electronic' => $item['electronic'] ?? false,
                    'startDate' => $item['startDate'] ?? null,
                    'endDate' => $item['endDate'] ?? null,
                    'totalPages' => $item['totalPages'] ?? null,
                    'batery' => $item['batery'] ?? false,
                    'shelf' => $item['shelf'] ?? false,
                    'level' => $item['level'] ?? false,
                    'stock' => $item['stock'] ?? null,
                    'observation' => $item['observation'] ?? null,
                ];

                // Check if the item has an ID (update)
                if (isset($item['id']) && !empty($item['id'])) {
                    // Find the existing procedure
                    $procedure = Procedure::find($item['id']);

                    if ($procedure) {
                        // Update the existing procedure
                        $procedure->update($data);
                        $procedures[] = $procedure;
                    } else {
                        // Handle case where ID is provided but procedure doesn't exist
                        // Option 1: Create new with the provided ID (may cause issues if ID exists)
                        // $data['id'] = $item['id'];
                        // $procedures[] = Procedure::create($data);

                        // Option 2: Skip or return error
                        throw new Exception("Procedure with ID {$item['id']} not found");
                    }
                } else {
                    // Create new procedure
                    $procedures[] = Procedure::create($data);
                }
            }

            return ApiResponse::success($procedures, "Registros procesados correctamente");
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
    public function index()
    {   
        try {
            $procedures = Procedure::latest();
            if (Auth::user()->role=='administrador') {

                $procedures =   $procedures->get();
            }
            else{
                $procedures =   $procedures->where('user_id', Auth::user()->id)->where('departament_id',Auth::user()->departament_id)->get();
            }
            return ApiResponse::success($procedures, 'Procesos obtenidos correctamente');
        } catch (Exception $e) {
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }

}
