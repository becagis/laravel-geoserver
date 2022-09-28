<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories\Facades;

use BecaGIS\LaravelGeoserver\Http\Repositories\GeoFeatureRepository;
use Illuminate\Support\Facades\Facade;

class GeoFeatureRepositoryFacade extends Facade {
    protected static function getFacadeAccessor() {
        return GeoFeatureRepository::class;
    }
}