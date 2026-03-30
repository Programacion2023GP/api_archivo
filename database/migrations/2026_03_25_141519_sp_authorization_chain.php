<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the procedure if it already exists
        DB::statement('DROP PROCEDURE IF EXISTS sp_authorization_chain');

        // Create the stored procedure
        DB::statement("
            CREATE PROCEDURE sp_authorization_chain(IN dept_id INT)
           BEGIN
    WITH RECURSIVE auth_path AS (
        -- Anchor: empezar desde el departamento que consultas
        SELECT 
            d.id,
            d.departament_id,
            d.name,
            d.authorized,
            u.id as user_id,
            u.fullName as director_name,
            0 as nivel,
            CAST(d.id AS CHAR(1000)) as path
        FROM archivo.departaments d
        LEFT JOIN users u ON d.id = u.departament_id AND u.role = 'director'
        WHERE d.id = dept_id 
        -- NO filtramos por authorized aquí para incluir el departamento inicial
        
        UNION ALL
        
        -- Recursivo: subir al padre (departament_id apunta al padre)
        SELECT 
            p.id,
            p.departament_id,
            p.name,
            p.authorized,
            pu.id as user_id,
            pu.fullName as director_name,
            ap.nivel + 1,
            CONCAT(ap.path, ' <- ', p.id)
        FROM auth_path ap
        INNER JOIN archivo.departaments p ON ap.departament_id = p.id  -- Subir al padre
        LEFT JOIN users pu ON p.id = pu.departament_id AND pu.role = 'director'
  WHERE p.authorized = 1    )
    SELECT 
        id as department_id,
        user_id,
        nivel as level,
        
        name as `group`,
        CASE 
            WHEN director_name IS NOT NULL THEN director_name
            ELSE CONCAT('(Sin director - ', name, ')')
        END as name,
        authorized,
        path as hierarchy_path
    FROM auth_path
    where authorized = 1
    ORDER BY nivel ASC;  -- nivel 0 es el departamento original, nivel 1 es su padre, etc.
END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP PROCEDURE IF EXISTS sp_authorization_chain');
    }
};
