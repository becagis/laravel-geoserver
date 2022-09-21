<?php

use BecaGIS\LaravelGeoserver\Http\Controllers\GeoRestController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/api/georest/{typeName}', [GeoRestController::class, 'list'])
                ->name('api.georest.list')->middleware('web');
Route::get('/api/georest/{typeName}/search', [GeoRestController::class, 'search'])
                ->name('api.georest.search')->middleware('web');
Route::put('/api/georest/{typeName}/{fid}', [GeoRestController::class, 'update'])
                ->name('api.georest.update')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);
Route::post('/api/georest/{typeName}', [GeoRestController::class, 'store'])
                ->name('api.georest.store')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);
Route::delete('/api/georest/{typeName}/{fid}', [GeoRestController::class, 'delete'])
                ->name('api.georest.delete')->middleware('web')->withoutMiddleware([VerifyCsrfToken::class]);