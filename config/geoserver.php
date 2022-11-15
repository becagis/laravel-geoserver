<?php

// config for LaravelGeoserver/LaravelGeoserver
//VALUE_DEFAULT_INT=-99999
//VALUE_DEFAULT_DATE=1900-01-01
return [
    'nodetools_url' => env('NODE_TOOLS_URL'),
    'value_default_int' => env('VALUE_DEFAULT_INT', -9999),
    'value_default_date' => env('VALUE_DEFAULT_DATE', '1900-01-01'),
    'geoserver_datastore' => env('GEODATABASE_DATABASE', 'geoportal_data'),
    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => env('RABBITMQ_PORT', 5672),
        'username' => env('RABBITMQ_USERNAME', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'exchange' => env('RABBiTMQ_EXCHANGE', '')
    ]
];
