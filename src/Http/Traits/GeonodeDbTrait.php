<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;
use Illuminate\Support\Facades\DB;

trait GeonodeDbTrait {
    public $DbGeonode = "geonode";
    public $DbGeonodeData = "geodatabase";

    public function getDbConnection() {
        return DB::connection($this->DbGeonode);
    }

    public function getDbShpConnection() {
        return DB::connection($this->DbGeonodeData);
    }
}