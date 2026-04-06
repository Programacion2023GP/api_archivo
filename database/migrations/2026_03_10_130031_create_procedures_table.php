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
            // $table->string('fileNumber')->nullable();
            // $table->string('archiveCode')->nullable();
            $table->integer('year')->nullable();

            $table->foreignId('process_id')->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('reviewed_by')
            ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('departament_id')->index();
            $table->text('description')->nullable();
            $table->boolean('fisic')->default(false);
            $table->boolean('electronic')->default(false);
            $table->boolean('administrative_value')->default(false);
            $table->boolean('accounting_fiscal_value')->default(false);
            $table->boolean('legal_value')->default(false);
            $table->integer('retention_period_current')->default(false);
            $table->integer('retention_period_archive')->default(false);
            $table->boolean('location_building')->default(false);
            $table->boolean('location_furniture')->default(false);
            $table->boolean('location_position')->default(false);
            $table->text('errorDescriptionField')->nullable();
            $table->text('errorFieldsKey')->nullable();
            $table->boolean('error')->default(false);

            $table->date('startDate')->nullable();
            $table->date('endDate')->nullable();
            $table->integer('totalPages')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('statu_id')->index();

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
