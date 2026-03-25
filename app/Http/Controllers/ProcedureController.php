<?php

namespace App\Http\Controllers;

use App\Models\ApiResponse;
use App\Models\Departament;
use App\Models\Proccess;
use App\Models\Procedure;
use App\Models\ProceduresCreatedAt;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcedureController extends Controller
{
    public function getAuthorizationChain($departament_id)
    {
        try {
            // Llamar al stored procedure
            $results = DB::select('CALL sp_authorization_chain(?)', [$departament_id]);

            // Ver los resultados
          
            return ApiResponse::success($results, "Registros procesados correctamente");
        } catch (Exception $e) {
            return ApiResponse::error('error', 500);
        }
    }
    public function index()
    {
        try {
            $procedures = ProceduresCreatedAt::query();

            if (strtolower(Auth::user()->role) == 'administrador') {
                // Administrador ve todos
            } else if (strtolower(Auth::user()->role) == 'director') {
                $procedures = $procedures->where('departament_id', Auth::user()->departament_id);
            } else {
                $procedures = $procedures->where('user_id', Auth::user()->id)
                    ->where('departament_id', Auth::user()->departament_id);
            }

            $procedures = $procedures->orderByDesc('order_date')->get();

            // Agregar cadena de autorización a cada procedimiento
            $procedures->each(function ($procedure) {
                $procedure->authorization_chain = DB::select('CALL sp_authorization_chain(?)', [$procedure->departament_id]);
            });

            return ApiResponse::success($procedures, 'Procesos obtenidos correctamente');
        } catch (Exception $e) {
            return ApiResponse::error('ocurrio un error', 500);
        }
    }


    public function createOrUpdate(Request $request)
    {
        try {
            $items = $request->all(); // arreglo que envía React
            $procedures = [];
            $processes = Proccess::select('id', 'departament_id')->get();
            foreach ($items as $item) {
                $process = $processes->firstWhere('id', $item['process_id']);

                $data = [
                    'year' => $item['year'] ?? null,

                    'boxes' => $item['boxes'] ?? null,
                    // 'fileNumber' => $item['fileNumber'] ?? null,
                    // 'archiveCode' => $item['archiveCode'] ?? null,
                    'process_id' => $item['process_id'] ?? null,
                    'user_id' => Auth::user()->id,
                    'departament_id' => $process ? $process->departament_id : Auth::user()->departament_id, // Usa el departamento del proceso o el del usuario
                    'description' => $item['description'] ?? null,
                    'fisic' => $item['fisic'] ?? false,
                    'electronic' => $item['electronic'] ?? false,
                    'startDate' => $item['startDate'] ?? null,
                    'endDate' => $item['endDate'] ?? null,
                    'totalPages' => $item['totalPages'] ?? null,
                    'administrative_value' => $item['administrative_value'] ?? false,
                    'accounting_fiscal_value' => $item['accounting_fiscal_value'] ?? false,
                    'legal_value' => $item['legal_value'] ?? false,
                    'retention_period_current' => $item['retention_period_current'] ?? false,
                    'retention_period_archive' => $item['retention_period_archive'] ?? false,
                    'location_building' => $item['location_building'] ?? false,
                    'location_furniture' => $item['location_furniture'] ?? false,
                    'location_position' => $item['location_position'] ?? false,
                    'observation' => $item['observation'] ?? null,
                    'errorDescriptionField' => $item['errorDescriptionField'] ?? null,
                    'error' => !empty($item['error']) ? false : !empty($item['errorDescriptionField']),
                    'statu_id' => !empty($item['error']) ? 2 : ($item['statu_id'] ?? 2),
                    'errorFieldsKey' => $item['errorFieldsKey'] ?? null,

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
                        $data['id'] = $item['id'];
                        $procedures[] = Procedure::create($data);

                        // Option 2: Skip or return error
                        // throw new Exception("Procedure with ID {$item['id']} not found");
                    }
                } else {
                    // Create new procedure
                    $procedures[] = Procedure::create($data);
                }
            }

            return ApiResponse::success($procedures, "Registros procesados correctamente");
        } catch (Exception $e) {
            return ApiResponse::error('ocurrio un error', 500);
        }
    }


    public function buildPaths($allDepartments): array
    {
        $paths      = [];
        $namePaths  = [];
        $rootNames  = [];
        $rootCodes  = []; // ← classification_code del padre raíz

        foreach ($allDepartments as $department) {
            if (is_null($department->departament_id)) {
                $paths[$department->id]     = $department->abbreviation;
                $namePaths[$department->id] = null;
                $rootNames[$department->id] = $department->name;
                $rootCodes[$department->id] = $department->classification_code; // ← él mismo es raíz
            } else {
                $paths[$department->id]     = $this->buildPathWithCache($department, $allDepartments, $paths);
                $namePaths[$department->id] = $this->buildNamePathWithCache($department, $allDepartments, $namePaths);
                $rootNames[$department->id] = $this->findRootName($department, $allDepartments);
                $rootCodes[$department->id] = $this->findRootCode($department, $allDepartments); // ← busca su raíz
            }
        }

        return [$paths, $namePaths, $rootNames, $rootCodes];
    }

    private function findRootCode($department, $allDepartments): string
    {
        $current = $department;

        while (!is_null($current->departament_id)) {
            $current = $allDepartments[$current->departament_id] ?? null;
            if ($current === null) break;
        }

        return $current?->classification_code ?? '';
    }

    private function findRootName($department, $allDepartments): string
    {
        $current = $department;

        while (!is_null($current->departament_id)) {
            $current = $allDepartments[$current->departament_id] ?? null;
            if ($current === null) break;
        }

        return $current?->name ?? '';
    }

    private function buildPathWithCache($department, $allDepartments, &$paths)
    {
        if (isset($paths[$department->id])) {
            return $paths[$department->id];
        }

        if (is_null($department->departament_id)) {
            $paths[$department->id] = $department->abbreviation;
            return $department->abbreviation;
        }

        $chain   = [];
        $current = $department;

        while ($current !== null) {
            if (isset($paths[$current->id])) {
                $base   = $paths[$current->id];
                $suffix = array_map(fn($d) => $d->abbreviation, array_reverse($chain));
                $path   = $suffix ? $base . '-' . implode('-', $suffix) : $base;

                $paths[$department->id] = $path;
                return $path;
            }

            $chain[]  = $current;
            $parentId = $current->departament_id;
            $current  = $parentId ? ($allDepartments[$parentId] ?? null) : null;
        }

        $root        = end($chain);
        $withoutRoot = array_slice($chain, 0, count($chain) - 1);
        $abbreviations = array_merge(
            [$root->abbreviation],
            array_map(fn($d) => $d->abbreviation, $withoutRoot)
        );

        $fullPath               = implode('-', $abbreviations);
        $paths[$department->id] = $fullPath;
        return $fullPath;
    }

    private function buildNamePathWithCache($department, $allDepartments, &$namePaths)
    {
        if (array_key_exists($department->id, $namePaths)) {
            return $namePaths[$department->id];
        }

        if (is_null($department->departament_id)) {
            $namePaths[$department->id] = null;
            return null;
        }

        $chain   = [];
        $current = $department;

        while ($current !== null) {
            if (array_key_exists($current->id, $namePaths)) {
                $base   = $namePaths[$current->id];
                $suffix = array_map(fn($d) => $d->name, array_reverse($chain));
                $path   = $base
                    ? $base . ',' . implode(',', $suffix)
                    : implode(',', $suffix);

                $namePaths[$department->id] = $path;
                return $path;
            }

            $chain[]  = $current;
            $parentId = $current->departament_id;
            $current  = $parentId ? ($allDepartments[$parentId] ?? null) : null;
        }

        $root        = end($chain);
        $withoutRoot = array_slice($chain, 0, count($chain) - 1);
        $names       = array_map(fn($d) => $d->name, $withoutRoot);

        $fullPath                   = implode(',', $names);
        $namePaths[$department->id] = $fullPath;
        return $fullPath;
    }
    public function detailsProcedure($created_at, $departament_id = 0)
    {
        try {
            $user = Auth::user();
            $allDepartments = Departament::all()->keyBy('id');

            [$paths, $namePaths, $rootNames, $rootCodes] = $this->buildPaths($allDepartments);

            $date = date('Y-m-d', strtotime($created_at));

            // Query base
            $query = Procedure::select('procedures.*', 'p.name as process', 's.name as status', 'p.classification_code', 'u.fullName as user_created')
                ->join('proccess as p', 'p.id', 'procedures.process_id')
                ->join('status as s', 's.id', 'procedures.statu_id')
                ->join('users as u', 'u.id', 'procedures.user_id')
                ->orderBy('procedures.id');

            // Aplicar filtros según el rol del usuario
            if ($user->role == 'administrador') {
                $query->where('procedures.departament_id', $departament_id);
            } else if (strtolower($user->role) == 'director') {
                $query->where('procedures.departament_id', $user->departament_id);
            } else {
                $query->where('procedures.departament_id', $user->departament_id);
            }

            // Filtrar por fecha
            $query->whereDate('procedures.created_at', $date);

            $allProcedures = $query->get();

            // Determinar el departamento a filtrar según rol
            $deptFilter = ($user->role == 'administrador') ? $departament_id : $user->departament_id;

            // Obtener el offset de registros anteriores al día consultado, agrupado por año
            $offsetByYear = Procedure::select('year', DB::raw('COUNT(*) as total'))
                ->where('departament_id', $deptFilter)
                ->whereDate('created_at', '<', $date)
                ->groupBy('year')
                ->pluck('total', 'year')
                ->toArray();

            $consecutiveByYear = [];

            $allProcedures = $allProcedures->map(function ($item) use (&$consecutiveByYear, $offsetByYear, $paths, $namePaths, $rootNames, $rootCodes) {
                $year = $item->year;

                if (!isset($consecutiveByYear[$year])) {
                    // Empieza desde el total de registros previos a este día
                    $consecutiveByYear[$year] = $offsetByYear[$year] ?? 0;
                }

                $consecutiveByYear[$year]++;
                $consecutive = str_pad($consecutiveByYear[$year], 3, '0', STR_PAD_LEFT);

                $deptId = $item->departament_id;
                $item->fileNumber  = ($paths[$deptId] ?? '') . '-' . $consecutive;
                $item->archiveCode = $rootCodes[$deptId] . "-" . $item->classification_code . "/" . ($paths[$deptId] ?? '') . '-' . $consecutive . '/' . $year;
                $item->serie       = $namePaths[$deptId] ?? '';
                $item->departament = $rootNames[$deptId] ?? '';

                return $item;
            });

            return ApiResponse::success($allProcedures, 'Procesos obtenidos correctamente');
        } catch (Exception $e) {
            return ApiResponse::error('ocurrio un error', 500);
        }
    }

    public function changeStatus(Request $request)
    {
        try {
            $procedure = Procedure::whereRaw('DATE(created_at) = ?', [Carbon::parse($request->startDate)->format('Y-m-d')])
                ->where('departament_id',$request->departament_id)->update(["statu_id" => $request->status]);
            return ApiResponse::success($procedure, 'Procesos actualizados correctamente');
        } catch (Exception $e) {
            return ApiResponse::error('ocurrio un error', 500);
        }
    }
}
