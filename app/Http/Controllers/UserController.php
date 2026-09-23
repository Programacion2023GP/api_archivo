<?php

namespace App\Http\Controllers;

use App\Models\ApiResponse;
use App\Models\Departament;
use App\Models\User;
use App\Models\UserPermission;
use Error;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use League\Config\Exception\ValidationException;

class UserController extends Controller
{
    public function signature(Request $request)
    {
        try {
            $user = User::find($request->id);
            if (!$user) {
                return ApiResponse::error('No se encontro el Enlace', 500);
            }

            if ($request->hasFile('signature')) {
                $archivo = $request->file('signature');

                // Guardar en el disco 'public'
                $rutaRelativa = $archivo->store('images', 'public');

                // Generar URL completa
                $urlCompleta = asset('storage/' . $rutaRelativa);

                $user->signature = $urlCompleta;
                $user->save();

                return ApiResponse::success($user, "Se agrego la firma");
            }

            return ApiResponse::error('No se envió ninguna firma', 400);
        } catch (Exception $e) {
            return ApiResponse::error('Ocurrio un error: ' . $e->getMessage(), 500);
        }
    }
    public function register(Request $request)
    {
        DB::beginTransaction();

        try {

            $isUpdate = $request->id > 0;

            $rules = [
                'firstName' => 'required|string|max:255',
                'paternalSurname' => 'required|string|max:255',
            ];

            $payrollRules = ['required', 'integer'];

            if (!$isUpdate) {
                $payrollRules[] = 'unique:users,payroll';
            } else {
                $payrollRules[] = 'unique:users,payroll,' . $request->id;
            }

            $rules['payroll'] = $payrollRules;

            $messages = [
                'payroll.unique' => 'El empleado ya está registrado',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);
            $validator->validate();

            $user = $isUpdate ? User::find($request->id) : new User();

            if ($isUpdate && !$user) {
                DB::rollBack();
                return ApiResponse::error('Enlace no encontrado', 404);
            }

            $rawPassword = null;

            // Guardar valores viejos para comparar después (solo en update)
            $oldRole = $isUpdate ? strtolower($user->role) : null;
            $oldDeptId = $isUpdate ? $user->departament_id : null;
            $newRole = strtolower($request->role);

            // Quien no tiene el permiso "sistemas" no puede asignar el rol Administrativo
            // ni otorgar el permiso "sistemas" a otro Enlace.
            $authHasSistemasPermission = DB::table('user_permissions')
                ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
                ->where('user_permissions.user_id', Auth::user()->id)
                ->where('permissions.name', 'sistemas')
                ->exists();

            if ($newRole === 'administrativo' && $oldRole !== 'administrativo' && !$authHasSistemasPermission) {
                DB::rollBack();
                return ApiResponse::error('No tiene permiso para asignar el rol Administrativo', 403);
            }

            if (!$isUpdate) {
                $rawPassword = $request->payroll;
                $user->password = Hash::make($rawPassword);
                if ($newRole === 'director') {
                    if (User::where('departament_id', $request->departament_id)->where('role', 'Director')->first()) {
                        return ApiResponse::error('Ya existe un director en el departamento', 404);
                    }
                }
            }
            $user->departament_id = $request->departament_id;
            $user->role = $request->role;
            $user->firstName = $request->firstName;
            $user->paternalSurname = $request->paternalSurname;
            $user->maternalSurname = $request->maternalSurname;
            $user->payroll = $request->payroll;
            $user->active = 1;

            $user->save();

            // ── Auto-autorizar / desautorizar departamento ──
            if ($isUpdate) {
                // Caso 1: era director y ya no → desautorizar viejo depto
                if ($oldRole === 'director' && $newRole !== 'director' && $oldDeptId) {
                    Departament::where('id', $oldDeptId)->update(['authorized' => 0]);
                }
                // Caso 2: cambio de departamento siendo director → desautorizar viejo, autorizar nuevo
                if ($newRole === 'director' && $oldDeptId != $request->departament_id) {
                    if ($oldDeptId) {
                        Departament::where('id', $oldDeptId)->update(['authorized' => 0]);
                    }
                    if ($request->departament_id) {
                        Departament::where('id', $request->departament_id)->update(['authorized' => 1]);
                    }
                }
                // Caso 3: sigue siendo director en el mismo depto → asegurar autorizado
                if ($newRole === 'director' && $request->departament_id) {
                    Departament::where('id', $request->departament_id)->update(['authorized' => 1]);
                }
            } else {
                // Creación: si es director, autorizar depto
                if ($newRole === 'director' && $request->departament_id) {
                    Departament::where('id', $request->departament_id)->update(['authorized' => 1]);
                }
            }

            if ($request->has('permissions')) {
                $permissionsToSave = collect($request->permissions)->map(fn($id) => (int) $id)->all();

                if (!$authHasSistemasPermission) {
                    $sistemasPermissionId = (int) DB::table('permissions')->where('name', 'sistemas')->value('id');

                    // Quien no tiene "sistemas" no puede otorgarlo, pero tampoco debe quitarlo
                    // sin querer si el Enlace editado ya lo tenía (no aparece en su lista para editar).
                    $targetAlreadyHadSistemas = $isUpdate && DB::table('user_permissions')
                        ->where('user_id', $user->id)
                        ->where('permission_id', $sistemasPermissionId)
                        ->exists();

                    $permissionsToSave = array_values(array_filter($permissionsToSave, fn($id) => $id !== $sistemasPermissionId));

                    if ($targetAlreadyHadSistemas) {
                        $permissionsToSave[] = $sistemasPermissionId;
                    }
                }

                app(UserPermissionController::class)->saveUserPermissions(
                    $user->id,
                    $permissionsToSave
                );
            }

            // 🔥 Si todo salió bien
            DB::commit();

            // Token después del commit
            $token = $user->createToken('auth_token')->plainTextToken;

            return ApiResponse::success([
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'password' => $rawPassword,
            ], $isUpdate ? 'Enlace actualizado con éxito' : 'Enlace registrado con éxito');
        } catch (ValidationException $e) {

            DB::rollBack();
            return ApiResponse::error($e->errors(), 422);
        } catch (QueryException $e) {

            DB::rollBack();

            if ($e->errorInfo[1] == 1062) {
                return ApiResponse::error('El numero de nomina ya está registrado', 500);
            }

            return ApiResponse::error('Ocurrió un error', 500);
        } catch (\Exception $e) {

            DB::rollBack();
            return ApiResponse::error('Error inesperado: ' . $e->getMessage(), 500);
        }
    }
    /**
     * Login de Enlace
     */
    public function login(Request $request)
    {
        $request->validate([
            'payroll' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('payroll', $request->payroll)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ApiResponse::error('Credenciales incorrectas', 401);
        }
        $permisos = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $user->id)
            ->pluck('permissions.name');        // Crear token
        $token = $user->createToken('auth_token', $permisos->toArray())->plainTextToken;

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
            'permisos' => $permisos,
            'token_type' => 'Bearer',
        ], 'Login exitoso');
    }

    /**
     * Logout (revocar token actual)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout exitoso');
    }
        public function index()
        {
            try {
                $authUserId = Auth::user()->id;
                $hasSistemasPermission = DB::table('user_permissions')
                    ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
                    ->where('user_permissions.user_id', $authUserId)
                    ->where('permissions.name', 'sistemas')
                    ->exists();

                $query = User::where('payroll', '!=', '000000')
                    ->leftJoin('departaments', 'departaments.id', '=', 'users.departament_id')
                    ->select(
                        'users.*',
                        'departaments.name as departament'
                    );

                // Sin el permiso "sistemas" no se ve ningún Enlace con rol Administrativo,
                // ni siquiera el propio registro.
                if (!$hasSistemasPermission) {
                    $query->whereRaw('LOWER(users.role) != ?', ['administrativo']);
                }

                $users = $query
                    ->orderBy('users.id','desc')
                    ->get()
                    ->map(function ($user) {
                        $userArray = $user->toArray();
                        // Obtener permisos del Enlace directamente
                        $permissionIds = DB::table('user_permissions')
                            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
                            ->where('user_permissions.user_id', $user->id)
                            ->pluck('permissions.id')
                            ->map(fn($id) => (int) $id)
                            ->toArray();
                        $userArray['permissions'] = $permissionIds;
                        return $userArray;
                    });

                return ApiResponse::success(
                    $users,
                    'Lista de Enlaces'
                );
        } catch (\Exception $th) {
            \Illuminate\Support\Facades\Log::error('Error cargando Enlaces: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString()
            ]);
            return ApiResponse::error(
                'No se pudo cargar los Enlaces: ' . $th->getMessage(),
                500
            );
            }
        }

    public function destroy(Request $request)
    {
        try {
            $technical = User::find($request->id);

            if (!$technical) {
                return ApiResponse::error('Enlace no encontrado', 404);
            }

            $technical->update(['active' => DB::raw('NOT active')]);;
            $technical->refresh();

            // Auto-desautorizar departamento cuando se desactiva un director
            if (strtolower($technical->role) === 'director' && !$technical->active && $technical->departament_id) {
                Departament::where('id', $technical->departament_id)->update(['authorized' => 0]);
            }

            return ApiResponse::success(
                null,
                $technical->active
                    ? 'Enlace activado correctamente'
                    : 'Enlace desactivado correctamente'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Error al eliminar el Enlace', 500);
        }
    }
}
