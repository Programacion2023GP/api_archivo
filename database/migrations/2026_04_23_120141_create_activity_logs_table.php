<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable'); // model_type + model_id
            $table->string('event');     // created, updated, deleted
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('user_name')->nullable(); // denormalizado por si el usuario se borra
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['loggable_type', 'loggable_id']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_logs');
    }
};
