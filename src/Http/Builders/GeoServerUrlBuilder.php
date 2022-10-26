<?php
namespace BecaGIS\LaravelGeoserver\Http\Builders;

use Illuminate\Support\Facades\Http;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class GeoServerUrlBuilder {
    public static function build() {
        return new GeoServerUrlBuilder([]);
    }

    public static function buildWithAccessToken($accessToken) {
        return new GeoServerUrlBuilder(['access_token' => $accessToken]);
    }

    public function urlRestFeatureType($featureType) {
        $urlParams = $this->urlParams() . $this->paramsString;
        return "{$this->geoserverUrl}/rest/workspaces/geonode/datastores/geoportal_data/featuretypes/{$featureType}.json?{$urlParams}";
    }

    protected function createUrlGeoFencInvalidateCache() {
        return "{$this->geoserverUrl}/rest/ruleCache/invalidate";
    }

    protected function invalidateCache() {
       //Http::get($this->createUrlGeoFencInvalidateCache());
    }

    public function prepareTransactions() {
        $this->invalidateCache();
    }

    protected $params = [];
    protected $paramsString = "";

    public function __construct($params) {
        $this->geoserverUrl = config("geonode.url")."/geoserver";
        $this->params = $params;
        $this->initDefault();
    }
    
    protected function initDefault() {
        $this->params = [
            ...$this->params,
            'service' => 'wfs',
            'version' => '2.0.0',
            'request' => 'getFeature',
            'outputFormat' => 'application/json'
        ];  
    }

    protected function urlParams() {
        return \http_build_query($this->params);
    }

    public function addParamsString($str) {
        $this->paramsString .= "&" . $str;
        return $this;
    }

    public function addParams($params) {
        $this->params = [
            ...$this->params,
            ...$params
        ];
        return $this;
    }

    public function removeParamKey($paramName) {
        unset($this->params[$paramName]);
        return $this;
    }

    public function url() {
        $urlParams = $this->urlParams() . $this->paramsString;
        return "{$this->geoserverUrl}/ows?{$urlParams}";
    }
}