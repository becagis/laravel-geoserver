<?php

namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Builders\GeoServerUrlBuilder;
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

    public function store($typeName, $data)
    {
        //$typeName = strtolower($typeName);
        return $this->actionVerifyGeonodeToken(function ($accessToken) use ($data, $typeName) {
            $data = $this->removePrimaryKey($data);
            if (empty($data)) {
                return $this->returnBadRequest();
            }
            $xml = WfsTransaction::build($typeName, 0)->addCreateProps($data)->xml();
            $apiUrl = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->url();
            $response = Http::contentType('text/plain')->send('POST', $apiUrl, [
                'body' => $xml
            ]);
            //dd($xml);
            
            return $this->handleHttpRequestRaw($response, function ($rd) use ($typeName, $data) {
                try {
                    $xmlJson = $this->convertWfsXmlToObj($rd->body());
                    if (isset($xmlJson->Exception)) {
                        return $this->returnBadRequest();
                    }
                    $fid = $xmlJson->InsertResults->Feature->FeatureId->attributes->fid;
                    if ($fid != null && sizeof(explode('.', $fid)) > 1) {
                        $res = $this->convertToRestifyCreateSuccessResponse($typeName, $fid, $data);
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

    public function getAttributeSet($typeName) {
        $typeName = strtolower($typeName);
        $sql = <<<EOD
            SELECT attribute, description, attribute_label, attribute_type, visible, display_order, featureinfo_type
            FROM public.layers_attribute left join layers_layer on layers_layer.resourcebase_ptr_id = layers_attribute.layer_id
            WHERE lower(typename) = lower(?) order by display_order
        EOD;
        $rows = $this->getDbConnection()->select($sql, [$typeName]);
        return $rows;
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
