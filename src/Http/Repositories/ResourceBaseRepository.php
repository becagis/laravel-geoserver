<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Builders\MapstoreMapJsonBuilder;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\HandleHttpRequestTrait;
use Exception;
use Illuminate\Support\Facades\Http;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class ResourceBaseRepository {
    use HandleHttpRequestTrait, GeonodeDbTrait;
    
    protected static $instance;
    public static function instance() {
        if (!isset($instance)) {
            $instance = new ResourceBaseRepository();
        }
        return $instance;
    }
    // $params => [name, center: [x,y,crs], projection: 'EPSG:4326', layers: []]
    public function creatMapResource($params) {
        $url = config('geonode.url');
        $accessToken = GeoNode::getAccessToken();
        $url = "$url/mapstore/rest/resources/?full=true&access_token=$accessToken";
        $json = MapstoreMapJsonBuilder::build()->setParams($params)->json();
        $http = Http::withToken($accessToken)->withBody($json, 'application/json')->post($url);
        $successCall = function($res) {
            return $res;
        };
        $failCall = function() use($http){
            return [];
        };
        return $this->handleHttpRequest($http, $successCall, $failCall);
    }

    public function deleteMapResource($resourceBasePtrId) {
        $url = config('geonode.url');
        $accessToken = GeoNode::getAccessToken();
        $url = "$url/api/v2/resources/{$resourceBasePtrId}/?access_token=$accessToken";
        $http = Http::withToken($accessToken)->delete($url);
        $successCall = function($res) {
            return $res;
        };
        $failCall = function() use($http){
            return [];
        };
        return $this->handleHttpRequest($http, $successCall, $failCall);
    }

    public function getPkColumnName($typeName) {
        $sql = <<<EOD
            select column_name from information_schema.table_constraints tco
            join information_schema.key_column_usage kcu 
                on kcu.constraint_name = tco.constraint_name
                and kcu.constraint_schema = tco.constraint_schema
                and kcu.constraint_name = tco.constraint_name
            where tco.constraint_type = 'PRIMARY KEY' and tco.table_name=?
        EOD;
        try {
            $tablename = explode(':', $typeName)[1];
            $rows = $this->getDbShpConnection()->select($sql, [$tablename]);
            return $rows[0]->column_name;
        } catch (Exception $ex) {
            return null;
        }
    }

    public function getPkColumnNameOfTypeName($typeName) {
        $sql = <<<EOD
            select column_name from information_schema.table_constraints tco
            join information_schema.key_column_usage kcu 
                on kcu.constraint_name = tco.constraint_name
                and kcu.constraint_schema = tco.constraint_schema
                and kcu.constraint_name = tco.constraint_name
            where tco.constraint_type = 'PRIMARY KEY' and tco.table_name=?
        EOD;
        try {
            $mapTypeName = WfsRepository::instance()->getMapFeatureTypeToTableName();
            
            $names = explode(':', $typeName);
            $typeName = sizeof($names) > 1 ? $names[1] : $names[0];
            $table = $mapTypeName[$typeName];
            $rows = $this->getDbShpConnection()->select($sql, [$table]);
            return $rows[0]->column_name;
        } catch (Exception $ex) {
            return null;
        }
    }
}