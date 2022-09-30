<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories\Facades;

use Becagis\LaravelGeoserver\Http\Repositories\ResourceBaseRepository;
use Illuminate\Support\Facades\Facade;

class ResourceBaseRepositoryFacade extends Facade {
    protected static function getFacadeAccessor() {
        return ResourceBaseRepository::class;
    }
}