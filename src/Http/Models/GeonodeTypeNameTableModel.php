<?php 
namespace BecaGIS\LaravelGeoserver\Http\Models;

use Illuminate\Database\Eloquent\Model;

class GeonodeTypeNameTableModel extends Model {
    public $timestamps = false;
    protected $table = 'geonode_typename_table';

    protected $fillable = [
        'typename', 
        'table',
        'data',
        'created_at'
    ];
}