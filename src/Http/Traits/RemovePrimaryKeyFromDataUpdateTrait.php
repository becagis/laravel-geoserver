<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

trait RemovePrimaryKeyFromDataUpdateTrait { 
    protected function removePrimaryKey($data) {
        $keyProps = ["fid"];
        foreach ($data as $name => $value) {
            if (in_array($name, $keyProps)) {
                unset($data[$name]);
            }
        }
        return $data;
    }
}