<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use TungTT\LaravelGeoNode\Facades\GeoNode;

trait ActionVerifyGeonodeTokenTrait { 
    protected function actionVerifyGeonodeToken($successCallback) {
        $accessToken = request('accessToken', null);
        $accessToken = $accessToken?? GeoNode::getAccessToken();
        if (isset($accessToken)) {
            return $successCallback($accessToken);
        } else {
            abort(403);
        }
    }
   
}