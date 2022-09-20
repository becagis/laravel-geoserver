<?php

namespace LaravelGeoserver\LaravelGeoserver\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LaravelGeoserver\LaravelGeoserver\LaravelGeoserver
 */
class LaravelGeoserver extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \LaravelGeoserver\LaravelGeoserver\LaravelGeoserver::class;
    }
}
