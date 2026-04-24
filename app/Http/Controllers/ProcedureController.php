<?php

namespace App\Http\Controllers;

use App\Models\ApiResponse;
use App\Models\Departament;
use App\Models\Proccess;
use App\Models\Procedure;
use App\Models\ProceduresCreatedAt;
use App\Models\SignaturesProcedure;
use App\Models\SignedByProcedure;
use App\Models\Status;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            $status = Status::where('active', 1)->get();
            $procedures = ProceduresCreatedAt::query();

            $userRole = strtolower(trim(Auth::user()->role));

            // Filtros por rol (sin cambios)
            if ($userRole == 'administrativo') {
                // administrador ve todos
            } else if ($userRole == 'director') {
                $directorDept = Departament::with('childrenRecursive')
                    ->find(Auth::user()->departament_id);
                if ($directorDept) {
                    $departmentIds = $this->getAllDepartmentIds($directorDept);
                    $procedures = $procedures->whereIn('departament_id', $departmentIds);
                } else {
                    $procedures = $procedures->where('departament_id', Auth::user()->departament_id);
                }
            } else {
                $procedures = $procedures->where('user_id', Auth::user()->id)
                    ->where('departament_id', Auth::user()->departament_id);
            }

            $procedures = $procedures->orderByDesc('order_date')->get();

            // Para cada procedimiento, construir la cadena completa
            $procedures->each(function ($procedure) use ($status) {
                // 1. Obtener firmantes requeridos desde el procedimiento almacenado
                $requiredSigners = $this->getRequiredSigners($procedure->departament_id);

                // 2. Obtener firmas ya realizadas
                $signed = SignedByProcedure::where('procedure_id', $procedure->id)
                    ->get()
                    ->keyBy('user_id'); // índice por user_id

                // 3. Construir la cadena combinada
                $chain = [];

                // Estados base (solicitud, revisión, etc.) – opcional, puedes mantenerlos
                $statusUpTo3 = $status->where('id', '<=', 3)
                    ->map(function ($item) use ($procedure) {
                        return [
                            'procedure_id' => 0,
                            'name' => strtoupper($item->name),
                            'active' => $item->active ?? 0,
                            'status' => $procedure->statu_id > $item->id ? 'completado' : 'pendiente',
                            'position' => $item->id,
                            'type' => 'base'
                        ];
                    })->values();
                $chain = array_merge($chain, $statusUpTo3->toArray());

                // 4. Agregar cada firmante requerido con su estado real
                foreach ($requiredSigners as $signer) {
                    $alreadySigned = $signed->has($signer['user_id']);
                    $chain[] = [
                        'procedure_id' => $procedure->id,
                        'user_id' => $signer['user_id'],
                        'name' => $signer['name'],
                        'group' => $signer['group'],
                        'level' => $signer['level'],
                        'status' => $alreadySigned ? 'completado' : 'pendiente',
                        'signed_at' => $alreadySigned ? $signed[$signer['user_id']]->created_at : null,
                        'type' => 'signature'
                    ];
                }

                $procedure->authorization_chain = $chain;
            });

            return ApiResponse::success($procedures, 'Procesos obtenidos correctamente');
        } catch (Exception $e) {
            return ApiResponse::error('Ocurrió un error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Ejecuta el procedimiento almacenado sp_authorization_chain
     * y devuelve los firmantes requeridos (directores + usuarios con signature_position)
     */
    private function getRequiredSigners($departament_id)
    {
        $results = DB::select('CALL sp_authorization_chain(?)', [$departament_id]);
        // Limpiar la conexión porque CALL puede dejar resultados pendientes (MySQL)
        DB::disconnect(); // o DB::connection()->getPdo()->nextRowset();
        return collect($results)->map(function ($row) {
            return [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'group' => $row->group,
                'level' => $row->level,
                'department_id' => $row->department_id
            ];
        })->filter(function ($signer) {
            // Solo los que tienen user_id válido (los grupos sin director tienen null)
            return !is_null($signer['user_id']);
        })->values()->toArray();
    }
    /**
     * Obtiene recursivamente todos los IDs de departamentos incluyendo hijos
     */
    private function getAllDepartmentIds($department, &$ids = [])
    {
        // Agregar el ID del departamento actual
        $ids[] = $department->id;

        // Recorrer los hijos recursivamente
        if ($department->childrenRecursive) {
            foreach ($department->childrenRecursive as $child) {
                $this->getAllDepartmentIds($child, $ids);
            }
        }

        return array_unique($ids); // Eliminar duplicados por si acaso
    }


    public function createOrUpdate(Request $request)
    {
        try {
            $items     = $request->all();
            $procedures = [];
            $processes  = Proccess::select('id', 'departament_id')->get();

            // Pre-cargar departamentos y construir paths UNA sola vez
            $allDepartments = Departament::all()->keyBy('id');
            [$paths, $namePaths, $rootNames, $rootCodes] = $this->buildPaths($allDepartments);

            // Contador de consecutivos por dept+year para este batch
            $consecutiveByYearDept = [];

            foreach ($items as $item) {
                $process       = $processes->firstWhere('id', $item['process_id']);
                $departamentId = $process ? $process->departament_id : Auth::user()->departament_id;
                $chain         = DB::select('CALL sp_authorization_chain(?)', [$departamentId]);
                $year          = $item['year'] ?? null;

                $data = [
                    'year'                      => $year,
                    'boxes'                     => $item['boxes']                     ?? null,
                    'process_id'                => $item['process_id']                ?? null,
                    'user_id'                   => Auth::user()->id,
                    'departament_id'            => $departamentId,
                    'description'               => $item['description']               ?? null,
                    'fisic'                     => $item['fisic']                     ?? false,
                    'electronic'                => $item['electronic']                ?? false,
                    'startDate'                 => $item['startDate']                 ?? null,
                    'endDate'                   => $item['endDate']                   ?? null,
                    'totalPages'                => $item['totalPages']                ?? null,
                    'administrative_value'      => $item['administrative_value']      ?? false,
                    'accounting_fiscal_value'   => $item['accounting_fiscal_value']   ?? false,
                    'legal_value'               => $item['legal_value']              ?? false,
                    'retention_period_current'  => $item['retention_period_current']  ?? false,
                    'retention_period_archive'  => $item['retention_period_archive']  ?? false,
                    'location_building'         => $item['location_building']         ?? null,
                    'location_furniture'        => $item['location_furniture']        ?? null,
                    'location_position'         => $item['location_position']         ?? null,
                    'observation'               => $item['observation']               ?? null,
                    'errorDescriptionField'     => $item['errorDescriptionField']     ?? null,
                    'error'                     => !empty($item['error'])             ?? false,
                    'statu_id'                  => !empty($item['errorDescriptionField']) ? 4 : ($item['statu_id'] ?? 2),
                    'errorFieldsKey'            => $item['errorFieldsKey']            ?? null,
                ];

                if (isset($item['id']) && !empty($item['id'])) {
                    $procedure = Procedure::find($item['id']);
                    if ($procedure) {
                        unset($data['user_id']);
                        $procedure->update($data);
                        $procedures[] = $procedure;
                        continue; // En update no recalculamos consecutivo
                    }
                }

                // ── Consecutivo al momento de crear ──────────────────────────
                $key = "{$departamentId}_{$year}";

                if (!isset($consecutiveByYearDept[$key])) {
                    // Total de registros YA existentes para ese dept+año
                    $consecutiveByYearDept[$key] = Procedure::where('departament_id', $departamentId)
                        ->where('year', $year)
                        ->count();
                }

                $consecutiveByYearDept[$key]++;
                $consecutive = str_pad($consecutiveByYearDept[$key], 3, '0', STR_PAD_LEFT);

                // Calcular y guardar directamente en el registro
                $classificationCode         = optional($process)->classification_code;
                $data['file_number']        = ($paths[$departamentId] ?? '') . '-' . $consecutive;
                $data['archive_code']       = ($rootCodes[$departamentId] ?? '')
                    . '-' . $classificationCode
                    . '/' . ($paths[$departamentId] ?? '')
                    . '-' . $consecutive
                    . '/' . $year;

                $procedure    = Procedure::create($data);
                $procedures[] = $procedure;

                foreach ($chain as $chainItem) {
                    SignaturesProcedure::create([
                        'procedure_id' => $procedure->id,
                        'user_id'      => $chainItem->user_id,
                    ]);
                }
            }

            return ApiResponse::success($procedures, "Registros procesados correctamente");
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
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
            $date = date('Y-m-d', strtotime($created_at));

            $query = Procedure::select(
                'procedures.*',
                'p.name as process',
                's.name as status',
                'u.signature as signature',
                'p.classification_code',
                'u.fullName as user_created',
                'r.fullName as reviewed_user',
                'r.signature as reviewed_signature'
            )
                ->leftJoin('proccess as p', 'p.id', 'procedures.process_id')
                ->leftJoin('status as s',   's.id', 'procedures.statu_id')
                ->leftJoin('users as u',    'u.id', 'procedures.user_id')
                ->leftJoin('users as r',    'r.id', 'procedures.reviewed_by')
                ->whereDate('procedures.created_at', $date)
                ->orderBy('procedures.id');

            $deptFilter = in_array(strtolower($user->role), ['administrativo', 'director'])
                ? $departament_id
                : $user->departament_id;

            $query->where('procedures.departament_id', $deptFilter);

            $allProcedures = $query->get();

            // ── Cache de firmas: convierte cada path UNA sola vez ────────────
            $signatureCache = [];

            $toBase64Cached = function (?string $path) use (&$signatureCache): ?string {
              
                    $signatureCache[$path] = $this->imageToBase64($path);
                
                return $signatureCache[$path];
            };

            $allProcedures = $allProcedures->map(function ($item) use ($toBase64Cached) {
                $item->fileNumber  = $item->file_number;
                $item->archiveCode = $item->archive_code;

                // Firma: una conversión por path único
                $item->signature_b64          = $toBase64Cached($item->signature);
                $item->reviewed_signature_b64 = $toBase64Cached($item->reviewed_signature);

                return $item;
            });

            return ApiResponse::success($allProcedures, 'Procesos obtenidos correctamente');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
    public function changeStatus(Request $request)
    {
        try {
            $updateData = [
                'statu_id' => $request->status
            ];

            if ($request->status == 3) {
                $updateData['reviewed_by'] = Auth::user()->id;
            }

            $procedure = Procedure::whereRaw('DATE(created_at) = ?', [
                Carbon::parse($request->startDate)->format('Y-m-d')
            ])
                ->where('departament_id', $request->departament_id)
                ->update($updateData);
            return ApiResponse::success($procedure, 'Procesos actualizados correctamente');
        } catch (Exception $e) {
            return ApiResponse::error('ocurrio un error', 500);
        }
    }
}
