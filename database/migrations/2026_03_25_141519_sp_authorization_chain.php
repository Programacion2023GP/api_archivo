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
                    SELECT 
                        d.id,
                        d.departament_id,
                        d.name,
                        d.authorized,
                        u.id as user_id,
                        u.fullName as director_name,
                        0 as nivel
                    FROM archivo.departaments d
                    LEFT JOIN users u ON d.id = u.departament_id AND u.role = 'director'
                    WHERE d.id = dept_id
                    
                    UNION ALL
                    
                    SELECT 
                        p.id,
                        p.departament_id,
                        p.name,
                        p.authorized,
                        pu.id as user_id,
                        pu.fullName as director_name,
                        ap.nivel + 1
                    FROM archivo.departaments p
                    INNER JOIN auth_path ap ON p.id = ap.departament_id
                    LEFT JOIN users pu ON p.id = pu.departament_id AND pu.role = 'director'
                    WHERE p.id IS NOT NULL
                )
                SELECT 
                    nivel as level,
                    name  as `group`,
                    director_name as name
                FROM auth_path
                WHERE user_id IS NOT NULL
                ORDER BY nivel ASC;
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
