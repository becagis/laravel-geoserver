<?php

namespace BecaGIS\LaravelGeoserver\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LaravelGeoserver\LaravelGeoserver\LaravelGeoserver
 */
class GeoServer extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \BecaGIS\LaravelGeoserver\LaravelGeoserver::class;
    }
}
