<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use Exception;
use stdClass;
use TungTT\LaravelGeoNode\Facades\GeoNode;

trait ActionVerifyGeonodeTokenTrait { 
    use ActionReturnStatusTrait;

    protected function actionVerifyGeonodeToken($successCallback) {
        $accessToken = $this->getAccessToken();
        if (isset($accessToken)) {
            return $successCallback($accessToken);
        } else {
            return $this->returnBadRequest("Không có quyền truy cập, hoặc token đã hết hạn");
        }
    }

    protected function actionVerifyGeonodeTokenAllowNone($successCallback) {
        $accessToken = $this->getParamAccessTokenFromRequest();
        $accessToken = $this->getAccessToken($accessToken);
        if (!isset($accessToken)) {
            $accessToken = '';
        }
        return $successCallback($accessToken);
    }

    protected function getParamAccessTokenFromRequest() {
        $accessToken = request('accessToken', null);
        $accessToken = $accessToken ?? request('access_token', null);
        return $accessToken;
    }

    protected function getAccessToken() {
        $accessToken = $this->getParamAccessTokenFromRequest();
        if (isset($accessToken)) {
            return GeoNode::getAccessToken($accessToken); 
        } else {
            return GeoNode::getAccessToken();
        }
        return null;
    } 

    protected function getUser() {
        $user = GeoNode::user();
        if (isset($user)) {
            return $user;
        } else {
            $accessToken = $this->getAccessToken();
            if (isset($accessToken)) {
                $tokenInfo = GeoNode::tokenInfo($accessToken);
                try {
                    $user = new stdClass();
                    $user->provider_id = $tokenInfo['user_id'];
                    $user->username = $tokenInfo['username'];
                    return $user;
                } catch (Exception $ex) {
                    
                }
            }
        }
        return null;
    }

    protected function getUserProviderId() {
        $user  = $this->getUser();
        if (isset($user)) {
            return $user->provider_id;
        }
        return -1;
    }
}   