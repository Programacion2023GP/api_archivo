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
            CREATE VIEW procedures_created_at AS
            SELECT 
            ANY_VALUE(CONCAT(`d`.`name`, ' . ', `p`.`created_at`)) AS `full_group`,
            `p`.`user_id` AS `user_id`,
            `s`.`name` AS `status`,
            `p`.`departament_id` AS `departament_id`,
            ANY_VALUE(`d`.`name`) AS `department_name`,
            ANY_VALUE(`u`.`firstName`) AS `user_name`,
            ANY_VALUE(`u`.`paternalSurname`) AS `user_lastname`,
            ANY_VALUE(CONCAT(`u`.`firstName`, ' ', `u`.`paternalSurname`)) AS `user_fullname`,
            ANY_VALUE(`p`.`created_at`) AS `order_date`,
            ANY_VALUE(DAYNAME(`p`.`created_at`)) AS `weekday`,
            COUNT(0) AS `total_procedures`
        FROM
            `procedures` `p`
            JOIN `departaments` `d` ON `d`.`id` = `p`.`departament_id`
            JOIN `users` `u` ON `u`.`id` = `p`.`user_id`
            JOIN `status` `s` ON `s`.`id` = `p`.`statu_id`
        GROUP BY 
            CAST(`p`.`created_at` AS DATE),
            `p`.`user_id`,
            `s`.`name`,
            `p`.`departament_id`  -- Added this column
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS procedures_created_at');
    }
};
