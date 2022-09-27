<?php

namespace BecaGIS\LaravelGeoserver;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LaravelGeoserver\LaravelGeoserver\Commands\LaravelGeoserverCommand;

class LaravelGeoserverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-geoserver')
            ->hasMigrations(['create_objects_recovery_table'])
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web');
    }
}
