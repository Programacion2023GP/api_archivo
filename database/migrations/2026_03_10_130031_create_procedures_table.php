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
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('boxes')->nullable();
            $table->string('fileNumber')->nullable();
            $table->string('archiveCode')->nullable();
            $table->foreignId('process_id')->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('departament_id')->index();
            $table->text('description')->nullable();
            $table->boolean('digital')->default(false);
            $table->boolean('electronic')->default(false);
            $table->boolean('batery')->default(false);
            $table->boolean('shelf')->default(false);
            $table->boolean('level')->default(false);
            $table->text('stock')->nullable();

            $table->date('startDate')->nullable();
            $table->date('endDate')->nullable();
            $table->integer('totalPages')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
