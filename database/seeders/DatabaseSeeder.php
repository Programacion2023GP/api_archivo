<?php

namespace Database\Seeders;

use Carbon\Carbon;
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
            ['name' => 'usuarios_subirfirmas', 'active' => true],
            ['name' => 'revisar', 'active' => true],


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
            ['name' => 'revisado', 'active' => true],
            
            // ['name' => 'finalizado', 'active' => true],
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
        DB::table('users')->insert([
            [
                'id' => 1,
                'firstName' => 'Administrador',
                'paternalSurname' => 'Administrador',
                'maternalSurname' => 'Administrador',
             
                'payroll' => 'admin',
                'role' => 'administrativo',
                'departament_id' => null,
                'password' => Hash::make('desarrollo'), // 🔥 especial
                'active' => 1,
                'created_at' => Carbon::parse('2026-03-31 14:13:01'),
                'updated_at' => Carbon::parse('2026-03-31 14:13:01'),
            ],
            [
                'id' => 2,
                'firstName' => 'NESTOR JOSUE',
                'paternalSurname' => 'PUENTES',
                'maternalSurname' => 'INCHAURREGUI',
                'payroll' => '612024',
                'role' => 'Administrativo',
                'departament_id' => 2,
                'password' => Hash::make('612024'),
                'active' => 1,
                'created_at' => Carbon::parse('2026-03-31 14:14:54'),
                'updated_at' => Carbon::parse('2026-03-31 14:14:54'),
            ],
            [
                'id' => 3,
                'firstName' => 'LUIS ANGEL',
                'paternalSurname' => 'GUTIERREZ',
                'maternalSurname' => 'HERNANDEZ',
              
                'payroll' => '612053',
                'role' => 'Director',
                'departament_id' => 2,
                'password' => Hash::make('612053'),
                'active' => 1,
                'created_at' => Carbon::parse('2026-03-31 14:15:19'),
                'updated_at' => Carbon::parse('2026-03-31 14:15:19'),
            ],
            [
                'id' => 4,
                'firstName' => 'YOSIMAR',
                'paternalSurname' => 'RODRIGUEZ',
                'maternalSurname' => 'PUGA',
                'payroll' => '612025',
                'role' => 'Director',
                'departament_id' => 1,
                'password' => Hash::make('612025'),
                'active' => 1,
                'created_at' => Carbon::parse('2026-03-31 14:15:36'),
                'updated_at' => Carbon::parse('2026-03-31 14:15:36'),
            ],
            [
                'id' => 5,
                'firstName' => 'JOVANNY',
                'paternalSurname' => 'ESTRADA',
                'maternalSurname' => 'RIVERA',
                'payroll' => '612022',
                'role' => 'Usuario',
                'departament_id' => 2,
                'password' => Hash::make('612022'),
                'active' => 1,
                'created_at' => Carbon::parse('2026-03-31 14:16:01'),
                'updated_at' => Carbon::parse('2026-03-31 14:16:01'),
            ],
        ]);
       
        // Asignar TODOS los permisos al admin
     
        DB::table('departaments')->insert([
            [
                'id' => 1,
                'name' => 'OFICIALIA MAYOR',
                'departament_id' => null,
                'abbreviation' => 'OR',
                'classification_code' => '7S',
                'authorized' => 0,
                'active' => 1,
                'created_at' => Carbon::parse('2026-03-31 13:57:59'),
                'updated_at' => Carbon::parse('2026-03-31 13:57:59'),
            ],
            [
                'id' => 2,
                'name' => 'INFORMATICA',
                'departament_id' => 1,
                'abbreviation' => 'IA',
                'classification_code' => 'SE1',
                'authorized' => 0,
                'active' => 1,
                'created_at' => Carbon::parse('2026-03-31 13:58:14'),
                'updated_at' => Carbon::parse('2026-03-31 13:58:14'),
            ],
        ]);
        DB::table('proccess')->insert([
            [
                'id' => 1,
                'classification_code' => '1.1',
                'name' => 'DOCUMENTACION',
                'description' => null,
                'departament_id' => 2,
                'at' => 1,
                'ac' => 1,
                'proccess_id' => null,
                'active' => 1,
                'created_at' => Carbon::parse('2026-03-31 13:58:29'),
                'updated_at' => Carbon::parse('2026-03-31 13:58:29'),
            ],
        ]);
        // Mostrar resumen

        $now = Carbon::now();

        // Get all permission IDs dynamically
        $allPermissionIds = DB::table('permissions')
            ->where('active', 1)
            ->pluck('id')
            ->toArray();

        // Define which permissions each user should have
        $userPermissions = [
            1 => $allPermissionIds,  // Admin gets ALL active permissions
            2 => [2, 4, 18],         // User 2: tramite_actualizar, tramite_ver, revisar
            3 => [1, 2, 3, 4],       // User 3: Full tramite permissions
            4 => [1, 2, 3, 4],       // User 4: Full tramite permissions
            5 => [1, 2, 3, 4],       // User 5: Full tramite permissions
        ];

        foreach ($userPermissions as $userId => $permissionIds) {
            // Remove existing permissions for this user
            DB::table('user_permissions')
                ->where('user_id', $userId)
                ->delete();

            // Insert new permissions
            foreach ($permissionIds as $permissionId) {
                DB::table('user_permissions')->insert([
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    
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