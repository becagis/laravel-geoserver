<?php
namespace BecaGIS\LaravelGeoserver\Http\Controllers;

use BecaGIS\LaravelGeoserver\Http\Resources\WfsTransaction;
use BecaGIS\LaravelGeoserver\Http\Traits\ActionReturnStatusTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\ConvertGeoJsonToRestifyTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\HandleHttpRequestTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\ActionVerifyGeonodeTokenTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\RemovePrimaryKeyFromDataUpdateTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\XmlConvertTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class GeoRestController extends BaseController {
    use ConvertGeoJsonToRestifyTrait, ActionVerifyGeonodeTokenTrait, HandleHttpRequestTrait, ActionReturnStatusTrait, XmlConvertTrait, RemovePrimaryKeyFromDataUpdateTrait;

    protected $geoserverUrl = "";
    protected $geoRestUrl = "";
    protected $defaultPerPage = 20; 

    public function __construct() {
        $this->geoserverUrl = config("geonode.url")."/geoserver";
        $this->geoRestUrl = URL::to("/api/georest");
    }

    public function list(Request $request, $typeName) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use ($request, $typeName) {
            $validator = Validator::make($request->all(), [
                'page' => 'integer',
                'cql_filter' => 'string'
            ]);
            
            $validated = $validator->validated();
            $page = $validated['page'] ?? 1;
            $perPage = $this->defaultPerPage;
            $startIndex = $perPage * ($page - 1);

            $url = "{$this->geoserverUrl}/ows?" 
                        . "service=WFS&version=2.0.0&request=GetFeature&typeNames={$typeName}" 
                        . "&access_token={$accessToken}&outputFormat=application/json&count={$perPage}&startIndex={$startIndex}";

            $cql_filter = $validated['cql_filter']?? '';
            if (!empty($cql_filter)) {
                $url .= "&cql_filter={$cql_filter}";
            }

            $response =  Http::get($url);
            return $this->handleHttpRequest($response, 
                function($data) use($typeName, $page, $perPage) {
                    $apiUrl = "{$this->geoRestUrl}/{$typeName}";
                    return $this->convertGeoJsonToRestifyResponse($typeName, $apiUrl, $data, $page, $perPage);
                }, 
                function() use($typeName, $page, $perPage) {
                    $apiUrl = "{$this->geoRestUrl}/{$typeName}";
                    return $this->convertGeoJsonToRestifyResponse($typeName, $apiUrl, [], $page, $perPage);
                }
            );
        });
    }

    public function search(Request $request, $typeName) {
        return $this->list($request, $typeName);
    }

    public function update(Request $request, $typeName, $fid) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use ($request, $typeName, $fid) {
            $data = $request->post();
            $data = $this->removePrimaryKey($data);
            if (empty($data)) {
                return $this->returnBadRequest();
            }
            $xml = WfsTransaction::build($typeName, $fid)->addUpdateProps($data)->xml();
            $apiUrl = "{$this->geoserverUrl}/ows?access_token={$accessToken}&outputFormat=application/json";
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
                        return $this->convertToRestifyCreateSuccessResponse($typeName, $fid, $data);
                    }
                } catch (Exception $ex) {
                    return $this->returnBadRequest();
                }
            });
        }); 
    }

    public function store(Request $request, $typeName) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use ($request, $typeName) {
            $data = $request->post();
            $data = $this->removePrimaryKey($data);
            if (empty($data)) {
                return $this->returnBadRequest();
            }
            $xml = WfsTransaction::build($typeName, 0)->addCreateProps($data)->xml();
            $apiUrl = "{$this->geoserverUrl}/ows?access_token={$accessToken}&outputFormat=application/json";
            $response = Http::contentType('text/plain')->send('POST',$apiUrl, [
                'body' => $xml
            ]);
            
            return $this->handleHttpRequestRaw($response, function($rd) use ($typeName, $data) {
                try {
                    $xmlJson = $this->convertWfsXmlToObj($rd->body());
                    if (isset($xmlJson->Exception)) {
                        throw new Exception();
                    }
                    $fid = $xmlJson->InsertResults->Feature->FeatureId->attributes->fid;
                    if ($fid != null) {
                        return $this->convertToRestifyCreateSuccessResponse($typeName, $fid, $data);
                    }
                } catch (Exception $ex) {
                    return $this->returnBadRequest();
                }
            });
        });
    }

    public function delete(Request $request, $typeName, $fid) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use ($request, $typeName, $fid) {
            $xml = WfsTransaction::build($typeName, $fid)->addDelete()->xml();
            $apiUrl = "{$this->geoserverUrl}/ows?access_token={$accessToken}&outputFormat=application/json";
            $response = Http::contentType('text/plain')->send('POST',$apiUrl, [
                'body' => $xml
            ]);
            
            return $this->handleHttpRequestRaw($response, function($rd) use ($typeName, $fid) {
                try {
                    $xmlJson = $this->convertWfsXmlToObj($rd->body());
                    if (isset($xmlJson->Exception)) {
                        throw new Exception(json_encode($xmlJson->Exception));
                    }
                    return $this->returnNoContent();
                } catch (Exception $ex) {
                    return $this->returnBadRequest();
                }
            });
        }); 
    }
}   