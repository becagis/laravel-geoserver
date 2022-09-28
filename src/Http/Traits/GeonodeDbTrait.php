<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;
use Illuminate\Support\Facades\DB;

trait GeonodeDbTrait {
    public $DbGeonode = "geonode";
    public $DbGeonodeData = "geodatabase";
    public $DbPSQL = "pgsql";

    public function getDbConnection() {
        return DB::connection($this->DbGeonode);
    }

    public function getDbShpConnection() {
        return DB::connection($this->DbGeonodeData);
    }

    public function getDb($dbString) {
        return DB::connection($dbString);
    }

    public function getDbPSQL() {
        return DB::connection($this->DbPSQL);   
    }

    public function getWorkSpace() {
        $rows = $this->getDbConnection()->select("select * from layers_layer limit 1");
        if (sizeof($rows) > 0) {
            return $rows[0]->workspace;
        }
        return "";
    }
}