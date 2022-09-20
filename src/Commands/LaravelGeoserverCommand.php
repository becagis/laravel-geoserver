<?php

namespace LaravelGeoserver\LaravelGeoserver\Commands;

use Illuminate\Console\Command;

class LaravelGeoserverCommand extends Command
{
    public $signature = 'laravel-geoserver';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
