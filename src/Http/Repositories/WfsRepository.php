<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Builders\GeoServerUrlBuilder;
use BecaGIS\LaravelGeoserver\Http\Builders\MapstoreMapJsonBuilder;
use BecaGIS\LaravelGeoserver\Http\Models\GeonodeTypeNameTableModel;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\HandleHttpRequestTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\XmlConvertTrait;
use Exception;
use Illuminate\Support\Facades\Http;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class WfsRepository {
    use 
    HandleHttpRequestTrait,
    XmlConvertTrait,
    GeonodeDbTrait;

    protected static $ins;
    protected $cacheMapFeatureTypeToTableName;
    public static function instance() {
        if (!isset(self::$ins)) {
            self::$ins = new WfsRepository();
        } 
        return self::$ins;
    }

    public function getMapTableNameToFeatureType() {
        $mapFeatureTypes = $this->getMapFeatureTypeToTableName();
        $result = [];
        foreach ($mapFeatureTypes as $featureType => $tableName) {
            $prefix = "";
            $split = explode(':', $featureType);
            if (sizeof($split) == 2) {
                $prefix = $split[0] . ":";
            }
            $result[$prefix.$tableName] = $featureType;
        }
        return $result;
    }

    public function verifyMissingFeatureTable($listMissingFeatureTable) {
        //return $listMissingFeatureTable;
        $result = [];
        $cachedFeatureTables = GeonodeTypeNameTableRepository::instance()->getMapFeatureTypeTable();
        foreach ($listMissingFeatureTable as $feature) {
            if (isset($cachedFeatureTables[$feature])) {
                $result[$feature] = $cachedFeatureTables[$feature];
            } else {
                try {
                    $accessToken = GeoNode::getAccessToken();
                    $url = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->urlRestFeatureType($feature);
                    $http = Http::get($url);
                    $nativeName = $this->handleHttpRequest($http, 
                        function($data) {
                            return data_get($data, 'featureType.nativeName', null);
                        }, 
                        function () {
                            return null;
                    });
                    if (isset($nativeName)) {
                        GeonodeTypeNameTableRepository::instance()->storeCache($feature, $nativeName);
                        $result[$feature] = $nativeName;
                    }
                } catch (Exception $ex) {
                }
            }
        } 
        return $result;
    }

    public function getMapFeatureTypeToTableName() {
        if (isset($this->cacheMapFeatureTypeToTableName)) {
            return $this->cacheMapFeatureTypeToTableName;
        }
        try {
            $rows = $this->getDbConnection()->select("select * from layers_layer where \"storeType\" = 'dataStore'");
            $mapTableName = [];
            $types = [];
            foreach($rows as $row) {
                $type = $row->typename;
                $split = explode(':', $type);
                if (sizeof($split) == 2) {
                    $type = $split[1];
                    $substrcheck = strtolower(substr($type, strlen($type) - 4, 4));
                    if ($substrcheck == 'type') {
                        $type = substr($type, 0, strlen($type) - 4);
                    }
                    array_push($types, $type);
                }
            }
            $mapTableName = $this->verifyMissingFeatureTable($types);
            $this->cacheMapFeatureTypeToTableName = $mapTableName;         
            return $mapTableName;
        } catch (Exception $ex) {
            return [];
        }
    }

    public function __getMapFeatureTypeToTableName() {
        if (isset($this->cacheMapFeatureTypeToTableName)) {
            return $this->cacheMapFeatureTypeToTableName;
        }
        $accessToken = GeoNode::getAccessToken();
        $url = GeoServerUrlBuilder::buildWithAccessToken($accessToken)
                        ->removeParamKey("outputFormat")
                        ->addParams([
                            'request' => 'DescribeFeatureType'
                        ])->url();
        $http = Http::get($url);
        return $this->handleHttpRequestRaw($http, function($rd) {
            try {
                $mapTableName = []; 
                $json = $this->convertWfsXmlToObj($rd->body());
                $elements = $json->element;
                $types = [];
                foreach($elements as $element) {
                    $attributes = $element->attributes;
                    $name = $attributes->name;
                    $type = $attributes->type;
                    $split = explode(':', $type);
                    if (sizeof($split) == 2) {
                        $type = $split[1];
                        $substrcheck = strtolower(substr($type, strlen($type) - 4, 4));
                        if ($substrcheck == 'type') {
                            $type = substr($type, 0, strlen($type) - 4);
                        }
                        array_push($types, $type);
                    }
                }
                $mapTableName = $this->verifyMissingFeatureTable($types);
                $this->cacheMapFeatureTypeToTableName = $mapTableName;         
                return $mapTableName;
            } catch (Exception $ex) {
                return [];
            }
        });
    }

    public function _getMapFeatureTypeToTableName() {
        //$this->_getMapFeatureTypeToTableName();
        if (isset($this->cacheMapFeatureTypeToTableName)) {
            return $this->cacheMapFeatureTypeToTableName;
        }
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
                $this->cacheMapFeatureTypeToTableName = $mapTableName;    
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
                $lower = $name;
                if (isset($mapFeatureTypes[$lower])) {
                    array_push($tables, $mapFeatureTypes[$lower]);
                }
            }
            return $tables;
        } catch (Exception $ex) {
            return $featureTypes;
        }   
    }

    public function getTableNamesMapByFeatureTypes($featureTypes) {
        try {
            $mapFeatureTypes = $this->getMapFeatureTypeToTableName();
            $tables = [];
            foreach ($featureTypes as $featureType) {
                $name = $featureType;
                $split = explode(':', $name);
                if (sizeof($split) == 2) {
                    $name = $split[1];
                }
                $lower = $name;
                if (isset($mapFeatureTypes[$lower])) {
                    $tables[$mapFeatureTypes[$lower]] = $lower;
                }
            }   
            return $tables;
        } catch (Exception $ex) {
            return $featureTypes;
        }   
    }

    
}