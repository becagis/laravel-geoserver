<?php
namespace BecaGIS\LaravelGeoserver\Http\Resources;

use BecaGIS\LaravelGeoserver\Http\Repositories\Facades\GeoFeatureRepositoryFacade;
use BecaGIS\LaravelGeoserver\Http\Repositories\GeoFeatureRepository;
use BecaGIS\LaravelGeoserver\Http\Traits\WfsGeomTrait;
use Exception;

class WfsTransaction {
    use WfsGeomTrait;

    protected $deletes, $updates, $creates, $updateGeoms, $createGeoms;
    protected $typeName, $fid, $attributeSetMap;
    protected $geomProps = ["geom", "geometry", "the_geom"];

    // exp: build('genode:qhpksdd_quan12', 'qhpksdd_quan12.1')
    public static function build($typeName, $fid) {
        $wfsTransaction =  new WfsTransaction();
        $wfsTransaction->typeName = $typeName;
        $wfsTransaction->fid = $fid;

        $wfsTransaction->attributeSetMap = GeoFeatureRepositoryFacade::getAttributeSetMap($typeName);

        return $wfsTransaction;
    }

    public function checkAttrIsInt($attributeName) {
        if (isset($this->attributeSetMap[$attributeName])) {
            return $this->attributeSetMap[$attributeName] == "xsd:int" ||
            $this->attributeSetMap[$attributeName] == "xsd:float" ||
            $this->attributeSetMap[$attributeName] == "xsd:double";
        }
        return true;
    }

    public function __construct() {
        $this->deletes = [];
        $this->updates = [];
        $this->creates = [];
        $this->createGeoms = [];
        $this->updateGeoms = [];
    }

    // exp: $name => $geojson
    public function addUpdateGeoJson($name, $geojson) {
        $geom = $this->getGeomFromGeojson($name, $geojson);
        if ($geom != null) {
            array_push($this->updateGeoms, $geom);
        }
        return $this;
    }

    // exp: $name => $geojson
    public function addCreateGeoJson($name, $geojson) {
        $geom = $this->getGeomFromGeojson($name, $geojson);
        if ($geom != null) {
            array_push($this->createGeoms, $geom);
        }
        return $this;
    }

    protected function getGeomFromGeojson($name, $geojson) {
        if (is_string($geojson)) {
            $geojson = json_decode($geojson);
        }
        $geojson = (object)$geojson;
        $value = $this->extractCoordinatesFromGeoJson($geojson);
        if ($value != null) {
            return (object)[
                'typeName' => $this->typeName,
                'geomType' => $geojson->type,
                'fid' => $this->fid,
                'name' => $name,
                'value' => $value
            ];
        }
        return null;
    }
    

    // $geojson -> json_decoded;return posList or pos String. "long lat long lat ...."
    protected function extractCoordinatesFromGeoJson($geojson) {
        try {
            $result = "";
            $coords = $geojson->coordinates;
            $extractCoords = function ($coord) use(&$result, &$extractCoords) {
                if (is_array($coord)) {
                    foreach ($coord as $coord1) {
                        $extractCoords($coord1);
                    }
                } else {
                    $result .= $coord . " ";
                }
            };
            $extractCoords($coords);
            return $result;
        } catch (Exception $e) {
            return null;
        }
    }

    // exp: addCreateProp(name: 'matdo', value: '')
    public function addCreateProp($name, $value) {
        if ($this->checkAttrIsInt($name) & (!is_numeric($value) || $value = "")) {
            return $this;
        }
        if (in_array($name, $this->geomProps)) {
            $this->addCreateGeoJson($name, $value);
        } else {
            array_push($this->creates, (object)[
                'typeName' => $this->typeName,
                'fid' => $this->fid,
                'name' => $name,
                'value' => $value
            ]);
        }

        return $this;
    }

    // exp: addCreateProps([name => value]) : [matdo => 1, dientich=>2]
    public function addCreateProps($mapPropValue) { 
        foreach($mapPropValue as $name => $value) {
            $this->addCreateProp($name, $value);
        }
        
        return $this;
    }

    // exp: addUpdateProp(name: 'matdo', value: '')
    public function addUpdateProp($name, $value) {
        if ($this->checkAttrIsInt($name) & (!is_numeric($value) || $value = "")) {
            $value = "-99999";
        }

        if (in_array($name, $this->geomProps)) {
            $this->addUpdateGeoJson($name, $value);
        } else {
            array_push($this->updates, (object)[
                'typeName' => $this->typeName,
                'fid' => $this->fid,
                'name' => $name,
                'value' => $value
            ]);
        }
        return $this;
    }

    // exp: addUpdateProps([name => value]) : [matdo => 1, dientich=>2]
    public function addUpdateProps($mapPropValue) { 
        foreach($mapPropValue as $name => $value) {
            $this->addUpdateProp($name, $value);
        }
        
        return $this;
    }

    
    public function addDelete() {
        array_push($this->deletes, (object)[
            'typeName' => $this->typeName,
            'fid' => $this->fid
        ]);
        return $this;
    }

    protected function buildCreates() {
        $result = "";
        if (sizeof($this->creates) > 0 || sizeof($this->createGeoms) > 0) {
            $result .= "<wfs:Insert>\n";
            $result .= "<{$this->typeName}>\n";
            foreach ($this->creates as $create) {
                $result .= <<<EOD
                    <geonode:{$create->name}>{$create->value}</geonode:{$create->name}>\n
                EOD;
            }
            $result .= $this->buildCreateGeoms();
            $result .= "</{$this->typeName}>\n";
            $result .= "</wfs:Insert>";
        }
        return $result;
    }   

    protected function buildUpdates() {
        $result = "";
        if (sizeof($this->updates) > 0) {
            foreach ($this->updates as $update) {
                $result .= <<<EOD
                <wfs:Update typeName="{$update->typeName}">
                    <wfs:Property>
                        <wfs:Name>{$update->name}</wfs:Name>
                        <wfs:Value>{$update->value}</wfs:Value>
                    </wfs:Property>
                    <ogc:Filter>
                        <ogc:FeatureId fid="{$update->fid}" />
                    </ogc:Filter>
                </wfs:Update>\n
                EOD;
            }
        }
        return $result;
    }

    // convert $this->deletes -> xml string
    protected function buildDeletes() {
        $result = "";
        if (sizeof($this->deletes) > 0) {
            foreach ($this->deletes as $delete) {
                $result .= <<<EOD
                <wfs:Delete typeName="{$delete->typeName}">
                    <ogc:Filter>
                        <ogc:FeatureId fid="{$delete->fid}" />
                    </ogc:Filter>
                </wfs:Delete>
                EOD;
            }
        }
        return $result;
    }

    public function xml() {
        $result = <<<EOD
        <wfs:Transaction service="WFS" version="1.1.0" xmlns:wfs="http://www.opengis.net/wfs"
            xmlns:gml="http://www.opengis.net/gml" xmlns:ogc="http://www.opengis.net/ogc"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.opengis.net/wfs"
            xmlns:geonode="http://www.geonode.org/">
            {$this->buildCreates()}
            {$this->buildDeletes()}
            {$this->buildUpdates()}
            {$this->buildUpdateGeoms()}
        </wfs:Transaction>
        EOD;
        return $result;
    }

    protected function buildCreateGeoms() {
        $result = "";
        if (sizeof($this->createGeoms) > 0) {
            foreach ($this->createGeoms as $geom) {
                $geomXML = match($geom->geomType) {
                    'Point' => $this->buildPoint($geom),
                    'MultiLineString' => $this->buildMultiLineString($geom),
                    'LineString' => $this->buildLineString($geom),
                    'MultiPolygon' => $this->buildMultiPolygon($geom),
                    'Polygon' => $this->buildPolygon($geom),
                    default => ''
                };
                if (!empty($geomXML)) {
                    $result .= <<<EOD
                            <geonode:{$geom->name}>{$geomXML}</geonode:{$geom->name}>\n
                    EOD;
                }
            }
        }
        return $result;
    }

    protected function buildUpdateGeoms() {
        $result = "";
        if (sizeof($this->updateGeoms) > 0) {
            foreach ($this->updateGeoms as $geom) {
                $geomXML = match($geom->geomType) {
                    'Point' => $this->buildPoint($geom),
                    'MultiLineString' => $this->buildMultiLineString($geom),
                    'LineString' => $this->buildLineString($geom),
                    'MultiPolygon' => $this->buildMultiPolygon($geom),
                    'Polygon' => $this->buildPolygon($geom),
                    default => ''
                };
                if (!empty($geomXML)) {
                    $result .= <<<EOD
                        <wfs:Update typeName="{$geom->typeName}">
                            <wfs:Property>
                                <wfs:Name>{$geom->name}</wfs:Name>
                                <wfs:Value>
                                    {$geomXML}
                                </wfs:Value>
                            </wfs:Property>
                            <ogc:Filter>
                                <ogc:FeatureId fid="{$geom->fid}" />
                            </ogc:Filter>
                        </wfs:Update>
                    EOD;
                }
            }
        }
        return $result;
    }
}