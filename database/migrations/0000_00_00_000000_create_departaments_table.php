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
        Schema::create('departaments', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->integer("departament_id")->nullable();
            $table->string('abbreviation')->nullable();
            $table->string('classification_code');

            $table->boolean("authorized")->default(false);
            $table->boolean("active")->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departaments');
    }
};
