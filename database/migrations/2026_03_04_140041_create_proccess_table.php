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
        Schema::create('proccess', function (Blueprint $table) {
            $table->id();
            $table->string('classification_code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('departament_id')->index();
            $table->integer('at');
            $table->integer('ac');
            $table->integer('total')->storedAs('at + ac');
            $table->integer("proccess_id")->nullable();
            $table->boolean("active")->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proccess');
    }
};
