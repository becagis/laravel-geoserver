<?php

namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Builders\GeoServerUrlBuilder;
use BecaGIS\LaravelGeoserver\Http\Repositories\Facades\ObjectsRecoveryRepositoryFacade;
use BecaGIS\LaravelGeoserver\Http\Resources\WfsTransaction;
use BecaGIS\LaravelGeoserver\Http\Traits\ActionReturnStatusTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\ActionVerifyGeonodeTokenTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\ConvertGeoJsonToRestifyTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\ConvertWfsTypeToLocalTypeTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\GeoFeatureTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\HandleHttpRequestTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\RemovePrimaryKeyFromDataUpdateTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\XmlConvertTrait;
use BecaGIS\LaravelGeoserver\Jobs\AMQBecaGISJob;
use Exception;
use Illuminate\Support\Facades\Http;
use TungTT\LaravelGeoNode\Facades\GeoNode as FacadesGeoNode;

class GeoFeatureRepository
{
    use
        HandleHttpRequestTrait,
        ActionVerifyGeonodeTokenTrait,
        ConvertGeoJsonToRestifyTrait,
        RemovePrimaryKeyFromDataUpdateTrait,
        ActionReturnStatusTrait,
        ConvertWfsTypeToLocalTypeTrait,
        XmlConvertTrait,
        GeoFeatureTrait,
        GeonodeDbTrait;

    public function get($typeName, $fid)
    {
        return $this->actionVerifyGeonodeTokenAllowNone(function ($accessToken) use ($typeName, $fid) {
            $successCallback = function ($data) use ($typeName) {
                try {
                    return $this->getRestData($typeName, $data)[0];
                } catch (Exception $ex) {
                    return (object)[];
                }
            };
            $failCallback = function () {
                throw new Exception();
            };
            try {
                $id = $this->getIdFromFid($fid);
                $urlApi = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->addParamsString("typeName={$typeName}&featureId={$id}")->url();
                $response = Http::get($urlApi);

                return $this->handleHttpRequest($response, $successCallback, $failCallback);
            } catch (Exception $ex) {
                return $failCallback();
            }
        });
    }

    function onStoreSuccess($typeName, $data) {
        try {
            AMQBecaGISJob::dispatch(AMQRepository::ChannelFeature, AMQRepository::ActionCreate, $data, [], $typeName);
        } catch (Exception $ex) {
        }
    }

    public function store($typeName, $data)
    {
        //$typeName = strtolower($typeName);
        return $this->actionVerifyGeonodeToken(function ($accessToken) use ($data, $typeName) {
            $data = $this->removePrimaryKeyOfTypeName($typeName, $data);
            if (empty($data)) {
                return $this->returnBadRequest();
            }
            $xml = WfsTransaction::build($typeName, 0)->addCreateProps($data)->xml();
            //dd($xml);
            $apiUrl = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->url();
            $response = Http::contentType('text/plain')->send('POST', $apiUrl, [
                'body' => $xml
            ]);

            return $this->handleHttpRequestRaw($response, function ($rd) use ($typeName, $data) {
                try {
                    $xmlJson = $this->convertWfsXmlToObj($rd->body());
                    if (isset($xmlJson->Exception)) {
                        return $this->returnBadRequest();
                    }
                    $fid = $xmlJson->InsertResults->Feature->FeatureId->attributes->fid;
                    if ($fid != null && sizeof(explode('.', $fid)) > 1) {
                        $res = $this->convertToRestifyCreateSuccessResponse($typeName, $fid, $data);
                        $this->onStoreSuccess($typeName, $res['data']);
                        return $res;
                    } else {
                        return $this->returnBadRequest();
                    }
                } catch (Exception $ex) {
                    return $this->returnBadRequest();
                }
            });
        });
    }

    function onUpdateSuccess($typeName, $data) {
        try {
            AMQBecaGISJob::dispatch(AMQRepository::ChannelFeature, AMQRepository::ActionUpdate, $data, [], $typeName);
        } catch (Exception $ex) {
            //dd($ex);
        }
    }

    public function update($typeName, $fid, $data) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use ($data, $typeName, $fid) {
            $data = $this->removePrimaryKeyOfTypeName($typeName, $data);
            if (empty($data)) {
                return $this->returnBadRequest();
            }
            $xml = WfsTransaction::build($typeName, $fid)->addUpdateProps($data)->xml();
            $apiUrl = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->url();
            $response = Http::contentType('text/plain')->send('POST',$apiUrl, [
                'body' => $xml
            ]);

            return $this->handleHttpRequestRaw($response, function($rd) use ($typeName, $data, $fid) {
                try {
                    $xmlJson = $this->convertWfsXmlToObj($rd->body());
                    if (isset($xmlJson->Exception)) {
                        throw new Exception(json_encode($xmlJson->Exception));
                    }
                    if ($fid != null) {
                        $res = $this->convertToRestifyCreateSuccessResponse($typeName, $fid, $data);
                        $this->onUpdateSuccess($typeName, $res['data']);
                        return $res;
                    }
                } catch (Exception $ex) {
                    return $this->returnBadRequest();
                }
            });
        });
    }

    function onDeleteSuccess($typeName, $data) {
        try {
            AMQBecaGISJob::dispatch(AMQRepository::ChannelFeature, AMQRepository::ActionDelete, $data, [], $typeName);
        } catch (Exception $ex) {
        }
    }

    public function delete($typeName, $fid) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use ($typeName, $fid) {
            $trash = ObjectsRecoveryRepositoryFacade::createRecoveryFromGeoDbFeature($typeName, $fid);
            $xml = WfsTransaction::build($typeName, $fid)->addDelete()->xml();
            $apiUrl = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->url();
            $response = Http::contentType('text/plain')->send('POST',$apiUrl, [
                'body' => $xml
            ]);

            return $this->handleHttpRequestRaw($response, function($rd) use ($typeName, $fid, $trash) {
                try {
                    $xmlJson = $this->convertWfsXmlToObj($rd->body());
                    if (isset($xmlJson->Exception)) {
                        throw new Exception(json_encode($xmlJson->Exception));
                    } else {
                        ObjectsRecoveryRepositoryFacade::setInTrash($trash);
                        $this->onDeleteSuccess($typeName, ['fid' => $fid]);
                        return $this->returnNoContent();
                    }
                } catch (Exception $ex) {
                    return $this->returnBadRequest();
                }
            });
        });
    }

    public function checkPerm($permName, $typeName) {
        $providerId = $this->getUserProviderId();
        $layers = PermRepositry::instance()->getActorPermsOnLayerUnit($providerId, 'user', $typeName);
        $permsArr = array_values($layers);
        $perms = [];
        foreach ($permsArr as $perm) {
            $perms = array_merge($perms, $perm['perms']);
        }
        return in_array($permName, $perms);
    }


    public function getAttributeSet($typeName) {
        try {
            if ($this->checkPerm('view_resourcebase', $typeName)) {
                $sql = <<<EOD
                    SELECT attribute, description, attribute_label, attribute_type, visible, display_order, featureinfo_type
                    FROM public.layers_attribute left join layers_layer on layers_layer.resourcebase_ptr_id = layers_attribute.layer_id
                    WHERE typename = ? order by display_order
                EOD;
                $rows = $this->getDbConnection()->select($sql, [$typeName]);
                return $rows;
            }
        } catch (Exception $ex) {
            return [];
        }
    }

    public function getAttributeSetMap($typeName) {
        $attributeSet = $this->getAttributeSet($typeName);
        $map = [];
        foreach ($attributeSet as $attribute) {
            $map[$attribute->attribute] = $attribute->attribute_type;
        }
        return $map;
    }
}
