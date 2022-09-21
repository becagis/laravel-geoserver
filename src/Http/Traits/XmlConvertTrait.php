<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

trait XmlConvertTrait {
    protected function convertWfsXmlToObj($wfsXml) {
        $xmlRemoveChars = ['wfs:', 'ogc:', '@', 'ows:'];
        foreach ($xmlRemoveChars as $char) {
            $wfsXml = str_replace($char, '', $wfsXml);
        }
        $xmlObj = simplexml_load_string($wfsXml);
        $xmlJson = (object)json_decode(str_replace('@', '', json_encode($xmlObj)));
        return $xmlJson;
    }
}