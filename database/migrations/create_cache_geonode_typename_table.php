<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('geonode_typename_table', function (Blueprint $table) {
            $table->id();
            $table->string('typename')->nullable();
            $table->string('table')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
};
