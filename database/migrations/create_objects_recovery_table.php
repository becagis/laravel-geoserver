<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('objects_recovery', function (Blueprint $table) {
            $table->id();
            $table->string('object_pk')->nullable();
            $table->string('object_type')->nullable();
            $table->text('data')->nullable();
            $table->text('geom')->nullable();
            $table->string('object_db')->nullable();
            $table->integer('status')->nullable();
            $table->text('meta')->nullable();
            $table->string('created_by')->nullable();
            $table->string('restored_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('restored_at')->nullable();
        });
    }

    public function down() {
        Schema::dropIfExists('objects_recovery');
    }
};
