<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop view if exists
        DB::statement('DROP VIEW IF EXISTS procedures_created_at');

        // Create view
        DB::statement("
CREATE  VIEW `procedures_created_at` AS WITH ranked_procedures AS (
    SELECT 
        CAST(p.created_at AS DATE) AS order_date,
        p.user_id,
        p.departament_id,
        p.id,
        d.name AS department_name,
        u.firstName AS user_name,
        u.paternalSurname AS user_lastname,
        u.fullName AS user_fullname,
        r.fullName AS reviewed_fullname,
        p.created_at AS order_date_full,
        DAYNAME(p.created_at) AS weekday,
        p.statu_id,
        p.reviewed_by,
        p.error,

        ROW_NUMBER() OVER (
            PARTITION BY CAST(p.created_at AS DATE), p.user_id, p.departament_id 
            ORDER BY p.id DESC
        ) AS rn,
        COUNT(*) OVER (
            PARTITION BY CAST(p.created_at AS DATE), p.user_id, p.departament_id
        ) AS total_procedures
    FROM procedures p
    JOIN departaments d ON d.id = p.departament_id
    JOIN users u ON u.id = p.user_id
    LEFT JOIN users r ON r.id = p.reviewed_by
)

SELECT 
    CONCAT(rp.department_name, ' . ', rp.order_date_full) AS full_group,
    rp.user_id,
    rp.id,
    COALESCE(
        (SELECT sbp.name FROM signedbyprocedure sbp WHERE sbp.procedure_id = rp.id AND sbp.signedBy = 1 ORDER BY sbp.id DESC LIMIT 1),
        (SELECT s.name FROM status s WHERE s.id = rp.statu_id)
    ) AS status,
    rp.departament_id,
    rp.department_name,
    rp.user_name,
    rp.user_lastname,
    rp.user_fullname,
    rp.reviewed_fullname,
    rp.reviewed_by,
    rp.order_date_full AS order_date,
    rp.weekday,
    rp.total_procedures,
    rp.error
FROM ranked_procedures rp
");
}
// WHERE rp.rn = 1

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS procedures_created_at');
    }
};
