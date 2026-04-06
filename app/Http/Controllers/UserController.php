<?php

namespace App\Http\Controllers;

use App\Models\ApiResponse;
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
                return ApiResponse::error('No se encontro el usuario', 500);
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
                return ApiResponse::error('Usuario no encontrado', 404);
            }

            $rawPassword = null;

            if (!$isUpdate) {
                $rawPassword = $request->payroll;
                $user->password = Hash::make($rawPassword);
                if ($request->role == 'Director') {
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

            if ($request->has('permissions')) {
                app(UserPermissionController::class)->saveUserPermissions(
                    $user->id,
                    $request->permissions
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
            ], $isUpdate ? 'Usuario actualizado con éxito' : 'Usuario registrado con éxito');
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
     * Login de usuario
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
            $users = User::where('payroll', '!=', '000000')
                ->leftJoin('user_permissions', 'users.id', '=', 'user_permissions.user_id')
                ->leftJoin('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
                ->leftJoin('departaments', 'departaments.id', '=', 'users.departament_id')

                ->select(
                    'users.*',
                    'departaments.name as departament',
                    DB::raw('GROUP_CONCAT(permissions.id) as permission_ids')
                )
                ->groupBy('users.id')
                ->get()
                ->map(function ($user) {
                    $userArray = $user->toArray();
                    // Convertir los IDs de permisos de string a array de números
                    $userArray['permissions'] = $user->permission_ids
                        ? array_map('intval', explode(',', $user->permission_ids))
                        : [];
                    return $userArray;
                });

            return ApiResponse::success(
                $users,
                'Lista de usuarios'
            );
        } catch (\Exception $th) {
            return ApiResponse::success(
                null,
                'No se pudo cargar los usuarios'
            );
        }
    }

    public function destroy(Request $request)
    {
        try {
            $technical = User::find($request->id);

            if (!$technical) {
                return ApiResponse::error('Usuario no encontrado', 404);
            }

            $technical->update(['active' => DB::raw('NOT active')]);;
            $technical->refresh(); // 🔥 Esto es lo que falta
            return ApiResponse::success(
                null,
                $technical->active
                    ? 'Usuario activado correctamente'
                    : 'Usuario desactivado correctamente'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Error al eliminar el usuario', 500);
        }
    }
}
