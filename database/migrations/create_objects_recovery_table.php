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

            $table->addColumn('varchar', 'object_pk');
            $table->addColumn('varchar', 'object_type');
            $table->addColumn('text', 'data');
            $table->addColumn('text', 'geometry');
            $table->addColumn('varchar', 'object_db');
            $table->addColumn('integer', 'status');
            $table->addColumn('text', 'meta');
            $table->addColumn('varchar', 'created_by');
            $table->addColumn('varchar', 'restored_by');
            $table->addColumn('timestamp', 'created_at');
            $table->addColumn('timestamp', 'restored_at');

            $table->timestamps();
        });
    }
};
