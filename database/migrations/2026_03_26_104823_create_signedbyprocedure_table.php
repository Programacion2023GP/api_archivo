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
        DB::statement("
      CREATE 
  
VIEW `signedbyprocedure` AS
       SELECT 
        `sp`.`id` AS `id`,
        `sp`.`user_id` AS `user_id`,
        `sp`.`procedure_id` AS `procedure_id`,
        `u`.`fullName` AS `name`,
        `d`.`name` AS `group`,
        `sp`.`signedBy` AS `signedBy`
    FROM
        ((`signatures_procedure` `sp`
        JOIN `users` `u` ON ((`u`.`id` = `sp`.`user_id`)))
        JOIN `departaments` `d` ON ((`d`.`id` = `u`.`departament_id`)))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signedbyprocedure');
    }
};
