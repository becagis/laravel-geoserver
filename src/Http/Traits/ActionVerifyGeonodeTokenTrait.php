<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use TungTT\LaravelGeoNode\Facades\GeoNode;

trait ActionVerifyGeonodeTokenTrait { 
    use ActionReturnStatusTrait;

    protected function actionVerifyGeonodeToken($successCallback) {
        $accessToken = request('accessToken', null);
        $accessToken = $accessToken?? GeoNode::getAccessToken();
        if (isset($accessToken)) {
            return $successCallback($accessToken);
        } else {
            return $this->returnBadRequest("Không có quyền truy cập");
        }
    }
}   