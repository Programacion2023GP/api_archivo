<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Desactivar foreign keys temporalmente
        Schema::disableForeignKeyConstraints();

        // Limpiar tablas
        DB::table('users')->truncate();
        DB::table('status')->truncate();

        DB::table('user_permissions')->truncate();
        DB::table('permissions')->truncate();
        
        // Reactivar foreign keys
        Schema::enableForeignKeyConstraints();

        // Crear permisos
        $permissions = [
            ['name' => 'tramite_crear', 'active' => true],
           ['name' => 'tramite_actualizar', 'active' => true],
            ['name' => 'tramite_eliminar', 'active' => true],
            ['name' => 'tramite_ver', 'active' => true],
            ['name' => 'usuarios_crear', 'active' => true],
            ['name' => 'usuarios_actualizar', 'active' => true],
            ['name' => 'usuarios_eliminar', 'active' => true],
            ['name' => 'usuarios_ver', 'active' => true],
            ['name' => 'catalogo_departamentos_ver', 'active' => true],
            ['name' => 'catalogo_departamentos_crear', 'active' => true],
            ['name' => 'catalogo_departamentos_actualizar', 'active' => true],
            ['name' => 'catalogo_departamentos_eliminar', 'active' => true],
            ['name' => 'catalogo_tramite_ver', 'active' => true],
            ['name' => 'catalogo_tramite_crear', 'active' => true],
            ['name' => 'catalogo_tramite_actualizar', 'active' => true],
            ['name' => 'catalogo_tramite_eliminar', 'active' => true],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission['name'],
                'active' => $permission['active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $status = [
            ['name' => 'captura', 'active' => true],
            ['name' => 'enviado', 'active' => true],
            ['name' => 'autorizado', 'active' => true],
            
            ['name' => 'finalizado', 'active' => true],
            ['name' => 'rechazado', 'active' => true],
        ];

        foreach ($status as $statu) {
            DB::table('status')->insert([
                'name' => $statu['name'],
                'active' => $statu['active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Verificar si el usuario ya existe
        $existingUser = DB::table('users')->where('payroll', 'admin')->first();
        
        if (!$existingUser) {
            // Crear usuario admin si no existe
            $userId = DB::table('users')->insertGetId([
                'firstName' => 'Administrador',
                'active' => 1,
                'paternalSurname' => 'Administrador',
                'maternalSurname' => 'Administrador',
                'payroll' => 'admin',
                'role' => 'administrador',
                'password' => Hash::make("desarrollo"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = $existingUser->id;
            $this->command->info('Usuario admin ya existe, ID: ' . $userId);
        }
       
        // Asignar TODOS los permisos al admin
        $permissionIds = DB::table('permissions')->pluck('id');
        $asignados = 0;
        
        foreach ($permissionIds as $permissionId) {
            // Verificar si ya tiene el permiso
            $existe = DB::table('user_permissions')
                ->where('user_id', $userId)
                ->where('permission_id', $permissionId)
                ->exists();
            
            if (!$existe) {
                DB::table('user_permissions')->insert([
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $asignados++;
            }
        }

        // Mostrar resumen
        $this->command->info('=================================');
        $this->command->info('PERMISOS CREADOS: ' . count($permissions));
        $this->command->info('PERMISOS ASIGNADOS: ' . $asignados);
        $this->command->info('USUARIO ADMIN ID: ' . $userId);
        $this->command->info('=================================');
        
        // Mostrar los permisos del admin
        $permisosAdmin = DB::table('user_permissions')
            ->join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
            ->where('user_permissions.user_id', $userId)
            ->select('permissions.name')
            ->get()
            ->pluck('name')
            ->toArray();
        
        $this->command->info('Permisos del admin:');
        foreach ($permisosAdmin as $permiso) {
            $this->command->line(' - ' . $permiso);
        }
        $this->command->info('=================================');
    }
}

// -- Primero, declara las variables
// SET @prefijo = 'catalogo_tramite';
// SET @usuario_id = 1;

// -- Crear los nombres de permisos con collation explícita
// SET @crear = CONCAT(@prefijo, '_crear') COLLATE utf8mb4_unicode_ci;
// SET @ver = CONCAT(@prefijo, '_ver') COLLATE utf8mb4_unicode_ci;
// SET @exportar = CONCAT(@prefijo, '_exportar') COLLATE utf8mb4_unicode_ci;
// SET @eliminar = CONCAT(@prefijo, '_eliminar') COLLATE utf8mb4_unicode_ci;

// -- Insertar permisos (IGNORE evita errores por duplicados)
// INSERT IGNORE INTO permissions (name, created_at, updated_at) VALUES 
// (@crear, NOW(), NOW()),
// (@ver, NOW(), NOW()),
// (@exportar, NOW(), NOW()),
// (@eliminar, NOW(), NOW());

// -- Asignar permisos al usuario usando OR con collation explícita
// INSERT IGNORE INTO user_permissions (user_id, permission_id, created_at, updated_at)
// SELECT @usuario_id, id, NOW(), NOW()
// FROM permissions 
// WHERE name = @crear 
//    OR name = @ver 
//    OR name = @exportar 
//    OR name = @eliminar;