<?php

namespace LaravelGeoserver\LaravelGeoserver;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LaravelGeoserver\LaravelGeoserver\Commands\LaravelGeoserverCommand;

class LaravelGeoserverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-geoserver')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web')
            ->hasMigration('create_laravel-geoserver_table')
            ->hasCommand(LaravelGeoserverCommand::class);
    }
}
