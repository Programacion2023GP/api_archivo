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
  CREATE VIEW `procedures_created_at` AS with `grouped_procedures` as (select cast(`p`.`created_at` as date) AS `order_date`,`p`.`user_id` AS `user_id`,`p`.`departament_id` AS `departament_id`,max(`p`.`id`) AS `max_id`,count(0) AS `total_procedures`,any_value(concat(`d`.`name`,' . ',`p`.`created_at`)) AS `full_group`,any_value(`d`.`name`) AS `department_name`,any_value(`u`.`firstName`) AS `user_name`,any_value(`u`.`paternalSurname`) AS `user_lastname`,any_value(concat(`u`.`firstName`,' ',`u`.`paternalSurname`)) AS `user_fullname`,any_value(`p`.`created_at`) AS `order_date_full`,any_value(dayname(`p`.`created_at`)) AS `weekday`,any_value(`p`.`statu_id`) AS `statu_id` from (((`procedures` `p` join `departaments` `d` on((`d`.`id` = `p`.`departament_id`))) join `users` `u` on((`u`.`id` = `p`.`user_id`))) left join `status` `s` on((`s`.`id` = `p`.`statu_id`))) group by cast(`p`.`created_at` as date),`p`.`user_id`,`p`.`departament_id`) select `gp`.`full_group` AS `full_group`,`gp`.`user_id` AS `user_id`,`gp`.`max_id` AS `id`,coalesce((select `sbp`.`name` from `signedbyprocedure` `sbp` where ((`sbp`.`procedure_id` = `gp`.`max_id`) and (`sbp`.`signedBy` = 1)) order by `sbp`.`id` desc limit 1),(select `s`.`name` from `status` `s` where (`s`.`id` = `gp`.`statu_id`))) AS `status`,`gp`.`departament_id` AS `departament_id`,`gp`.`department_name` AS `department_name`,`gp`.`user_name` AS `user_name`,`gp`.`user_lastname` AS `user_lastname`,`gp`.`user_fullname` AS `user_fullname`,`gp`.`order_date_full` AS `order_date`,`gp`.`weekday` AS `weekday`,`gp`.`total_procedures` AS `total_procedures` from `grouped_procedures` `gp`
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS procedures_created_at');
    }
};
