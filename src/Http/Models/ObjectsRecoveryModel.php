<?php 
namespace BecaGIS\LaravelGeoserver\Http\Models;

use Illuminate\Database\Eloquent\Model;

class ObjectsRecoveryModel extends Model {
    public static $STATUS_NOINTRASH = -1;
    public static $STATUS_INTRASH = 0;
    public static $STATUS_RESTORED = 1;

    public $timestamps = false;
    protected $table = 'objects_recovery';

    protected $fillable = [
        'object_pk', 
        'object_type',
        'data',
        'geom',
        'status',
        'created_at',
        'create_by',
        'restored_at',
        'restored_by',
        'object_db',
        'meta'
    ];
    
}