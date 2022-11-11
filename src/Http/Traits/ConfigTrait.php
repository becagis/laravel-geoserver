<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

trait ConfigTrait {
    protected $configDefaultValueInt = "value_default_int";
    protected $configDefaultValueDate = "value_default_date";
    protected $configGeoServerDataStore = "geoserver.geoserver_datastore";

    protected function getConfig($configName) {
        return config($configName);
    }
}