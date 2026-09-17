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

class SignatureProcedureController extends Controller
{
    public function signatureByUser(Request $request)
    {
        try {
            // Siempre firma el usuario autenticado (nunca el user_id que mande el cliente)
            $userId = Auth::user()->id;

            if (!$request->filled('startDate') || !$request->filled('departament_id')) {
                return ApiResponse::error('Faltan datos del trámite a firmar', 422);
            }

            // signatures_procedure.procedure_id apunta al trámite INDIVIDUAL (tabla procedures)
            $individualIds = Procedure::where('departament_id', $request->departament_id)
                ->whereRaw('DATE(created_at) = ?', [Carbon::parse($request->startDate)->format('Y-m-d')])
                ->pluck('id');

            if ($individualIds->isEmpty()) {
                return ApiResponse::error('No se encontraron trámites para firmar', 404);
            }

            // Cadena de autorización: nivel 0 = director de más bajo rango, nivel más alto = el de más arriba
            $chain = collect(DB::select('CALL sp_authorization_chain(?)', [$request->departament_id]));
            DB::disconnect();
            $chain = $chain->filter(fn($row) => !is_null($row->user_id))->values();

            $currentSigner = $chain->firstWhere('user_id', $userId);

            if (!$currentSigner) {
                return ApiResponse::error('No tiene una firma pendiente para este trámite', 403);
            }

            // No puede firmar si algún director de nivel inferior aún no ha firmado
            $lowerLevelUserIds = $chain->where('level', '<', $currentSigner->level)
                ->pluck('user_id')
                ->filter()
                ->values();

            if ($lowerLevelUserIds->isNotEmpty()) {
                $pendingLowerLevel = SignaturesProcedure::whereIn('procedure_id', $individualIds)
                    ->whereIn('user_id', $lowerLevelUserIds)
                    ->where('signedBy', 0)
                    ->exists();

                if ($pendingLowerLevel) {
                    return ApiResponse::error('Aún faltan firmas de directores de nivel inferior', 409);
                }
            }

            $signatureUser = SignaturesProcedure::where('user_id', $userId)
                ->where('signedBy', 0)
                ->whereIn('procedure_id', $individualIds)
                ->update(['signedBy' => true]);

            return ApiResponse::success($signatureUser, 'Firmado');
        } catch (Exception $e) {
            Log::error("signatureByUser : " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
    public function listAutorized(Request $request)
    {
        try {
            // Resolver los trámites del grupo: por fecha+departamento (preferido)
            // o por procedure_id directo (compatibilidad)
            if ($request->filled('startDate') && $request->filled('departament_id')) {
                $individualIds = Procedure::where('departament_id', $request->departament_id)
                    ->whereRaw('DATE(created_at) = ?', [Carbon::parse($request->startDate)->format('Y-m-d')])
                    ->pluck('id');
            } else {
                $individualIds = collect([$request->procedure_id]);
            }

            $results = DB::table('signatures_procedure')
                ->select('signatures_procedure.*', 'u.fullName', 'u.signature')
                ->join('users as u', 'u.id', '=', 'signatures_procedure.user_id')
                ->whereIn('signatures_procedure.procedure_id', $individualIds)
                ->where('signedBy', 1)
                ->get();

            $results = $results->map(function ($item) {
                // Convertir la firma a base64
                $item->signature_b64 = $this->imageToBase64($item->signature);

                // Opcional: mantener la URL original si la necesitas

                return $item;
            });
            return ApiResponse::success($results, null);
        } catch (Exception $e) {
            Log::error("signatureByUser : " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
}
