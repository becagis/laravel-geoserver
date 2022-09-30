<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Builders\MapstoreMapJsonBuilder;
use BecaGIS\LaravelGeoserver\Http\Traits\HandleHttpRequestTrait;
use Illuminate\Support\Facades\Http;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class ResourceBaseRepository {
    use HandleHttpRequestTrait;
    
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
        $url = "$url/mapstore/rest/resources/?full=true&token=$accessToken";
        $json = MapstoreMapJsonBuilder::build()->setParams($params)->json();
        $http = Http::withToken($accessToken)->withBody($json, 'application/json')->post($url);
        $successCall = function($res) {
            return $res;
        };
        $failCall = function() use($http){
            dd($http);
        };
        return $this->handleHttpRequest($http, $successCall, $failCall);
    }
}