<?php
namespace BecaGIS\LaravelGeoserver\Http\Builders;

use TungTT\LaravelGeoNode\Facades\GeoNode;

class MapstoreMapJsonBuilder {
    public static function build() {
        return new MapstoreMapJsonBuilder();
    }

    public static function buildWithAccessToken($accessToken) {
        $obj = new MapstoreMapJsonBuilder();
        $obj->accessToken = $accessToken;
        return $obj;
    }

    protected $accessToken;
    protected $geoPortalUrl;
    protected $params;

    public function __construct() {
        $this->geoPortalUrl = config("geonode.url");
        $this->params = [
            "name" => "TestName",
            "center" => '{"x": 109.16235217452044, "y": 15.858842694266393, "crs": "EPSG:4326"}', // {x: lng, y: lat, crs: "EPSG:4326"}
            "projection" => "EPSG:4326",
            "layers" => "[]"
        ];
        $this->accessToken = "";
    }

    public function setParams($params) {
        $this->params = [
            ...$this->params,
            ...$params
        ];
        return $this;
    }

    public function json() {
        return $this->getJSONTemplate();
    }
    

    protected function getJSONTemplate() {
        extract($this->params, EXTR_PREFIX_ALL, "params");
        $params_id_string = isset($params_id) ? ",\"id\": $params_id" : "";
        return <<<EOD
        {
            "name": "{$params_name}",
            "data": {
                "version": 2,
                "map": {
                    "center": {$params_center},
                    "maxExtent": [
                        -20037508.34,
                        -20037508.34,
                        20037508.34,
                        20037508.34
                    ],
                    "projection": "{$params_projection}",
                    "units": "m",
                    "zoom": 5,
                    "mapOptions": {
        
                    },
                    "layers": {$params_layers},
                    "groups": [
                        {
                            "id": "Default",
                            "title": "Default",
                            "expanded": true
                        }
                    ],
                    "backgrounds": [
        
                    ]
                },
                "catalogServices": {
                    "services": {
                        "Demo WMS Service": {
                            "autoload": false,
                            "title": "Demo WMS Service",
                            "type": "wms",
                            "url": "https://demo.geo-solutions.it/geoserver/wms"
                        },
                        "Demo WMTS Service": {
                            "autoload": false,
                            "title": "Demo WMTS Service",
                            "type": "wmts",
                            "url": "https://demo.geo-solutions.it/geoserver/gwc/service/wmts"
                        },
                        "GeoPortal Catalogue": {
                            "autoload": true,
                            "layerOptions": {
                                "tileSize": 512
                            },
                            "title": "GeoPortal Catalogue",
                            "type": "csw",
                            "url": "https://geoportal-uat.vntts.vn/catalogue/csw"
                        }
                    },
                    "selectedService": "GeoPortal Catalogue"
                },
                "widgetsConfig": {
                    "layouts": {
                        "xxs": [
        
                        ],
                        "md": [
        
                        ]
                    }
                },
                "mapInfoConfiguration": {
        
                },
                "dimensionData": {
        
                },
                "timelineData": {
        
                }
            },
            "attributes": [
                {
                    "type": "string",
                    "name": "title",
                    "value": "{$params_name}",
                    "label": "Title"
                },
                {
                    "type": "string",
                    "name": "abstract",
                    "label": "Abstract"
                }
            ]
            $params_id_string
        }
        
        EOD;
    }
}