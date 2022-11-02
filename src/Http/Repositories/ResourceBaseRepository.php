<?php
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Builders\MapstoreMapJsonBuilder;
use BecaGIS\LaravelGeoserver\Http\Traits\ActionVerifyGeonodeTokenTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\HandleHttpRequestTrait;
use Exception;
use Illuminate\Support\Facades\Http;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class ResourceBaseRepository {
    use HandleHttpRequestTrait, GeonodeDbTrait, ActionVerifyGeonodeTokenTrait;

    protected static $instance;
    public static function instance() {
        if (!isset($instance)) {
            $instance = new ResourceBaseRepository();
        }
        return $instance;
    }

    public function getUUIDByLayerTypeName($typename) {
        $sql = <<<EOD
            select * from base_resourcebase where id in (select resourcebase_ptr_id from layers_layer where typename = :typename)
        EOD;
        $rows = $this->getDbConnection()->select($sql, [$typename]);
        return sizeof($rows) > 0 ? $rows[0]->uuid : null;
    }
    // $params => [name, center: [x,y,crs], projection: 'EPSG:4326', layers: '[]', 'data' => MapData]
    public function creatMapResource($params) {
        $accessToken = $this->getAccessToken();
        $url = config('geonode.url');
        $url = "$url/mapstore/rest/resources/?full=true&access_token=$accessToken";
        
        $layers = $this->getMapStoreLayersStrFromMapData($params["data"]);
        $json = MapstoreMapJsonBuilder::build()
                                    ->setParams([
                                        "name" => $params["name"], 
                                        "layers" => $layers
                                    ])->json();
        $http = Http::withToken($accessToken)
                    ->withBody($json, 'application/json')
                    ->post($url);

        $successCall = function($res) {
            return $res;
        };
        $failCall = function() use($http){
            return [];
        };
        return $this->handleHttpRequest($http, $successCall, $failCall);
    }

    // $params => [resourcebase_ptr_id, name, center: [x,y,crs], projection: 'EPSG:4326', layers: '[]', 'data' => MapData]
    public function updateMapResource($params) {
        $url = config('geonode.url');
        $accessToken = $this->getAccessToken();
        $resourceBaseId = $params["resourcebase_ptr_id"];

        $url = "$url/mapstore/rest/resources/$resourceBaseId/?full=true&access_token=$accessToken";

        $layers = $this->getMapStoreLayersStrFromMapData($params["data"]);
        $json = MapstoreMapJsonBuilder::build()->setParams(["name" => $params["name"], "layers" => $layers, "id" => $resourceBaseId])->json();
        $http = Http::withToken($accessToken)->withBody($json, 'application/json')->put($url);
        $successCall = function($res) {
            return $res;
        };
        $failCall = function() use($http){
            return [];
        };
        return $this->handleHttpRequest($http, $successCall, $failCall);
    }

    public function getMapStoreLayersStrFromMapData($data) {
        $result = [];
        try {
            $layers = $data["layers"];

            foreach ($layers as $layer) {
                array_push($result, $this->getMapStoreLayer($layer));
            }
        } catch (Exception $ex) {
        }
        return json_encode($result);
    }

    //id-id, name-name, title-title, type-type, url-url, format, visibility:true,format: image/png, params
    public function getMapStoreLayer($mapsLayer) {
        $id = $mapsLayer["id"];
        $name = $mapsLayer["owsLayers"];
        $title = $mapsLayer["title"];
        $type = $mapsLayer["type"];
        $url = $mapsLayer["url"];
        $layerStr = <<<EOD
        {
            "id": "$id",
            "format": "image/png",
            "search": {
                "url": "https://geoportal-uat.vntts.vn/geoserver/wfs",
                "type": "wfs"
            },
            "name": "$name",
            "description": "No abstract provided",
            "title": "$title",
            "type": "$type",
            "url": "$url",
            "bbox": {
                "crs": "EPSG:4326",
                "bounds": {
                    "minx": 102.170435826,
                    "miny": 8.59975962975,
                    "maxx": 109.33526981,
                    "maxy": 23.3520633001
                }
            },
            "visibility": true,
            "singleTile": false,
            "allowedSRS": {},
            "dimensions": [],
            "hideLoading": false,
            "handleClickOnLayer": false,
            "useForElevation": false,
            "hidden": false,
            "tileSize": 512,
            "params": {}
        }
        EOD;
        return json_decode($layerStr);
    }

    public function deleteMapResource($resourceBasePtrId) {
        $url = config('geonode.url');
        $accessToken = $this->getAccessToken();
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
