<?php

use BecaGIS\LaravelGeoserver\Http\Controllers\GeoRestController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/api/georest/labels', [GeoRestController::class, 'geostatsLabels'])
                ->name('api.georest.geostats.labels')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);
Route::get('/api/georest/count-features', [GeoRestController::class, 'geoStatsCountFeatures'])
                ->name('api.georest.geostats.countFeatures')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);
Route::get('/api/georest/search', [GeoRestController::class, 'geostatsSearch'])
                ->name('api.georest.geostats.search')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);
Route::post('/api/georest/geostats', [GeoRestController::class, 'geostats'])
                ->name('api.georest.geostats')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);
Route::get('/api/georest/{typeName}', [GeoRestController::class, 'list'])
                ->name('api.georest.list')->middleware('web');
Route::get('/api/georest/{typeName}/getters/{getter}', [GeoRestController::class, 'getters'])
                ->name('api.georest.getters')->middleware('web');
Route::get('/api/georest/{typeName}/actions/{action}', [GeoRestController::class, 'actions'])
                ->name('api.georest.actions')->middleware('web');
Route::get('/api/georest/{typeName}/search', [GeoRestController::class, 'search'])
                ->name('api.georest.search')->middleware('web');
Route::get('/api/georest/{typeName}/{fid}', [GeoRestController::class, 'show'])
                ->name('api.georest.show')->middleware('web');
Route::put('/api/georest/{typeName}/{fid}', [GeoRestController::class, 'update'])
                ->name('api.georest.update')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);
Route::post('/api/georest/{typeName}', [GeoRestController::class, 'store'])
                ->name('api.georest.store')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);
Route::delete('/api/georest/{typeName}/{fid}', [GeoRestController::class, 'delete'])
                ->name('api.georest.delete')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);