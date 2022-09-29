<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use Exception;

trait GeoFeatureTrait {
    protected function getIdFromFid($fid) {
        $split = explode('.', $fid);
        try {
            return $split[1];
        } catch (Exception $ex) {
            return '';
        }
    }
}