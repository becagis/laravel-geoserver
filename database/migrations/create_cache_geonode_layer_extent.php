<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('geonode_layer_extent', function (Blueprint $table) {
            $table->id();
            $table->string('typename')->nullable();
            $table->addcolumn('geometry', 'the_geom');
            $table->timestamp('created_at')->nullable();
        });
    }
};
