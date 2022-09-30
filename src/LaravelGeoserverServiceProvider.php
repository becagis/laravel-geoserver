<?php

namespace BecaGIS\LaravelGeoserver;

use BecaGIS\LaravelGeoserver\Http\Repositories\GeoFeatureRepository;
use BecaGIS\LaravelGeoserver\Http\Repositories\ObjectsRecoveryRepository;
use Becagis\LaravelGeoserver\Http\Repositories\ResourceBaseRepository;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LaravelGeoserver\LaravelGeoserver\Commands\LaravelGeoserverCommand;

class LaravelGeoserverServiceProvider extends PackageServiceProvider
{
    // public function register() {
    //     // $this->app->singleton(GeoFeatureRepository::class);
    //     // $this->app->singleton(ObjectsRecoveryRepository::class);
    // }
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-geoserver')
            ->hasMigrations(['create_objects_recovery_table'])
            ->runsMigrations()
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web');
    }

    public function packageRegistered() {
        $this->app->singleton(GeoFeatureRepository::class);
        $this->app->singleton(ObjectsRecoveryRepository::class);
        $this->app->singleton(ResourceBaseRepository::class);
    }
}
