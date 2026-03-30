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
           $signatureUser = SignaturesProcedure::where('user_id',$request->user_id)->update(['signedBy'=>true]);

            return ApiResponse::success($signatureUser, 'Firmado');
        } catch (Exception $e) {
            Log::error("signatureByUser : " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
    public function listAutorized(Request $request)
    {
        try {
            $results = DB::table('signatures_procedure')
                ->select('signatures_procedure.*', 'u.fullName')
                ->join('users as u', 'u.id', '=', 'signatures_procedure.user_id')
                ->where('procedure_id', $request->procedure_id)
                ->where('signedBy', 1)
                ->get();

            return ApiResponse::success($results, 'Firmado');
        } catch (Exception $e) {
            Log::error("signatureByUser : " . $e->getMessage());
            return ApiResponse::error('Ocurrió un error', 500);
        }
    }
}
