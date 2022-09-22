<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

trait HandleHttpRequestTrait { 
    protected function handleHttpRequest($http, $successCallback, $failCallback) {
        $isSuccess = $http->successful();
        if ($isSuccess) {
            $data = $http->json();
            return $successCallback($data);
        }
        if (isset($failCallback)) {
            return $failCallback();
        }
        abort(404);
    }

    protected function handleHttpRequestRaw($http, $successCallback) {
        $isSuccess = $http->successful();
        if ($isSuccess) {
            $data = $http;
            return $successCallback($data);
        }
        abort(404);
    }
}