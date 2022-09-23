<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

trait WfsGeomTrait {
    protected function buildPoint($geom) {
        $result = <<<EOD
                    <gml:Point srsDimension="2" srsName="EPSG:4326">
                        <gml:pos>{$geom->value}</gml:pos>
                    </gml:Point>
        EOD;
        return $result;
    }

    protected function buildMultiLineString($geom) {
        return <<<EOD
                    <gml:MultiLineString srsName="EPSG:4326">
                        <gml:lineStringMember>
                            <gml:LineString srsName="EPSG:4326">
                                <gml:posList>{$geom->value}</gml:posList>
                            </gml:LineString>
                        </gml:lineStringMember>
                    </gml:MultiLineString>
        EOD;
    }

    protected function buildMultiPolygon($geom) {
        return <<<EOD
                    <gml:MultiPolygon srsName="EPSG:4326">
                        <gml:polygonMember>
                            <gml:Polygon srsName="EPSG:4326">
                                <gml:exterior>  
                                    <gml:LinearRing>
                                        <gml:posList>{$geom->value}</gml:posList>
                                    </gml:LinearRing>
                                </gml:exterior>
                            </gml:Polygon>
                        </gml:polygonMember>
                    </gml:MultiPolygon>
        EOD;
    }
}