<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

trait ConvertWfsTypeToLocalTypeTrait {
    public $mapTypeConvert = [
        "xsd:dateTime" => "date",
        "xsd:float" => "float",
        "xsd:double" => "float",
        "xsd:int" => "integer",
        "xsd:long" => "integer",
        "xsd:string" => "string",
        "gml:PointPropertyType" => "Point",
        "gml:LineStringPropertyType" => "LineString",
        "gml:MultiLineStringPropertyType" => "MultiLineString",
        "gml:PolygonPropertyType" => "Polygon",
        "gml:MultiPolygonPropertyType" => "MultiPolygon"
    ];

    protected function getLocalType($wfsType) {
        if (isset($this->mapTypeConvert[$wfsType])) {
            return $this->mapTypeConvert[$wfsType];
        } 
        return null;
    }
}