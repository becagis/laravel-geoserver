<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories\Facades;

use BecaGIS\LaravelGeoserver\Http\Repositories\ObjectsRecoveryRepository;
use Illuminate\Support\Facades\Facade;

class ObjectsRecoveryRepositoryFacade extends Facade {
    protected static function getFacadeAccessor() {
        return ObjectsRecoveryRepository::class;
    }
}