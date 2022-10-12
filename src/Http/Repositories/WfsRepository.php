<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Builders\GeoServerUrlBuilder;
use BecaGIS\LaravelGeoserver\Http\Builders\MapstoreMapJsonBuilder;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\HandleHttpRequestTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\XmlConvertTrait;
use Exception;
use Illuminate\Support\Facades\Http;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class WfsRepository {
    use 
    HandleHttpRequestTrait,
    XmlConvertTrait;

    protected static $instance;
    public static function instance() {
        if (!isset($instance)) {
            $instance = new WfsRepository();
        }
        return $instance;
    }

    public function getMapFeatureTypeToTableName() {
        $accessToken = GeoNode::getAccessToken();
        $url = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->addParams([
            'request' => 'GetCapabilities'
        ])->url();
        $http = Http::get($url);
        return $this->handleHttpRequestRaw($http, function($rd) {
            try {
                $json = $this->convertWfsXmlToObj($rd->body());
                if (isset($json->Exception)) {
                    throw new Exception(json_encode($json->Exception));
                }

                $featureTypeList = $json->FeatureTypeList ?? null;
                $featureTypes = $featureTypeList->FeatureType ?? [];
                $mapTableName = [];
                foreach ($featureTypes as $featureType) {
                    $keywords = $featureType->Keywords ?? null;
                    $keyword = $keywords->Keyword ?? null;
                    if (isset($keyword) && sizeof($keyword) == 2) {
                        $tableName = $keyword[0] == "features" ? $keyword[1] : $keyword[0];
                        $name = $featureType->Name;
                        $split = explode(':', $name);
                        if (sizeof($split) == 2) {
                            $name = $split[1];
                        }
                        $mapTableName[$name] = $tableName; 
                    }
                }
                return $mapTableName;
            } catch (Exception $ex) {
                return [];
            }
        });
    } 

    public function getTableNamesByFeatureTypes($featureTypes) {
        try {
            $mapFeatureTypes = $this->getMapFeatureTypeToTableName();
            $tables = [];
            foreach ($featureTypes as $featureType) {
                $name = $featureType;
                $split = explode(':', $name);
                if (sizeof($split) == 2) {
                    $name = $split[1];
                }
                $lower = strtolower($name);
                if (isset($mapFeatureTypes[$lower])) {
                    array_push($tables, $mapFeatureTypes[$lower]);
                } else {
                    array_push($tables, $featureType);
                }
            }
            return $tables;
        } catch (Exception $ex) {
            return $featureTypes;
        }
    }
}