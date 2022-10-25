<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use BecaGIS\LaravelGeoserver\Http\Repositories\GeonodeTypeNameTableRepository;
use BecaGIS\LaravelGeoserver\Http\Repositories\ResourceBaseRepository;
use BecaGIS\LaravelGeoserver\Http\Repositories\WfsRepository;

trait RemovePrimaryKeyFromDataUpdateTrait { 
    protected function removePrimaryKey($data) {
        $keyProps = ["fid", "fid_1"];
        foreach ($data as $name => $value) {
            if (in_array($name, $keyProps)) {
                unset($data[$name]);
            }
        }
        return $data;
    }

    protected function removePrimaryKeyOfTypeName($typename, $data) {
        $pkCol = ResourceBaseRepository::instance()->getPkColumnNameOfTypeName($typename);
        $keyProps = [$pkCol];
        foreach ($data as $name => $value) {
            if (in_array($name, $keyProps)) {
                unset($data[$name]);
            }
        }
        return $data;
    }
}