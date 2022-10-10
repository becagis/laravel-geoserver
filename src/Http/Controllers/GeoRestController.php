<?php
namespace BecaGIS\LaravelGeoserver\Http\Controllers;

use App\Facades\GeoNode;
use BecaGIS\LaravelGeoserver\Http\Builders\GeoServerUrlBuilder;
use BecaGIS\LaravelGeoserver\Http\Repositories\Facades\GeoFeatureRepositoryFacade;
use BecaGIS\LaravelGeoserver\Http\Repositories\Facades\ObjectsRecoveryRepositoryFacade;
use BecaGIS\LaravelGeoserver\Http\Repositories\GeoFeatureRepository;
use BecaGIS\LaravelGeoserver\Http\Repositories\PermRepositry;
use BecaGIS\LaravelGeoserver\Http\Repositories\ResourceBaseRepository;
use BecaGIS\LaravelGeoserver\Http\Resources\WfsTransaction;
use BecaGIS\LaravelGeoserver\Http\Traits\ActionReturnStatusTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\ConvertGeoJsonToRestifyTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\HandleHttpRequestTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\ActionVerifyGeonodeTokenTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\ConvertWfsTypeToLocalTypeTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\RemovePrimaryKeyFromDataUpdateTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\XmlConvertTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use TungTT\LaravelGeoNode\Facades\GeoNode as FacadesGeoNode;

class GeoRestController extends BaseController {
    use ConvertGeoJsonToRestifyTrait,
        ActionVerifyGeonodeTokenTrait,
        HandleHttpRequestTrait,
        ActionReturnStatusTrait,
        XmlConvertTrait,
        RemovePrimaryKeyFromDataUpdateTrait,
        ConvertWfsTypeToLocalTypeTrait,
        GeonodeDbTrait;

    protected $geoRestUrl = "";
    protected $defaultPerPage = 20;

    public function __construct() {
        $this->geoRestUrl = URL::to("/api/georest");
        $this->geoStatsUrl = config("geoserver.nodetools_url");
    }

    public function geoStatsLabels(Request $request) {
        $query = $request->get('query', '');
        $baseUrl = "{$this->geoStatsUrl}/pgstats/search/labels";

        $http = Http::get($baseUrl);
        return $this->handleHttpRequest($http, function($data) {
            return $data;
        }, function () {
            return $this->returnBadRequest();
        });
    }

    public function geoStatsCountFeatures(Request $request) {
        $layers = $request->get('layers', null);
        $tablePrefix = $this->getWorkSpace();

        $baseUrl = "{$this->geoStatsUrl}/pgstats/stats/count-features?tablePrefix=$tablePrefix";
        $baseUrl = isset($layers) ? "$baseUrl&layers=$layers" : $baseUrl;

        $http = Http::get($baseUrl);
        return $this->handleHttpRequest($http, function($data) {
            return $data;
        }, function () {
            return $this->returnBadRequest();
        });
    }

    public function geoStatsSearch(Request $request) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use($request){
            $query = $request->get('query', '');
            $page = $request->get('page', 0);
            $layers = $request->get('layers', null);

            $user = FacadesGeoNode::user();
            $userId = $user->provider_id;

            $listLayersCanAccess = PermRepositry::instance()->filterListLayerTypeNameCanAccess($userId, PermRepositry::ActorTypeUser, ['view_resourcebase'], $layers);
            $layers = implode(',', $listLayersCanAccess);
            $baseUrl = "{$this->geoStatsUrl}/pgstats/search/features?query=$query&page=$page";
            $baseUrl = isset($layers) ? "$baseUrl&layers=$layers" : $baseUrl;
    
            $http = Http::get($baseUrl);
            return $this->handleHttpRequest($http, function($data) {
                return $data;
            }, function () {
                return $this->returnBadRequest();
            });
        });
    }

    public function geostats(Request $request) {
        $typeValidator = Validator::make($request->all(), [
            'type' => 'string',
        ]);
        if ($typeValidator->fails()) return $typeValidator->errors();
        $type = $typeValidator->validated()['type'];

        $validator = null;
        if ($type == 'circle') {
            $validator = Validator::make($request->post(), [
                'lat' => 'numeric',
                'long' => 'numeric',
                'radius' => 'numeric',
            ]);
        } else if ($type == 'polygon') {
            $validator = Validator::make($request->post(), [
                'geojson' => 'required'
            ]);
        }

        if ($validator == null || $validator->fails()) {
            return $validator->errors();
        } else {
            $validated = $validator->validated();
        }

        $layers = $request->get('layers', null);

        $user = FacadesGeoNode::user();
        $userId = $user->provider_id;
        $listLayersCanAccess = PermRepositry::instance()->filterListLayerTypeNameCanAccess($userId, PermRepositry::ActorTypeUser, ['view_resourcebase'], $layers);
        
        $layers = implode(',', $listLayersCanAccess);
        $baseUrl = "{$this->geoStatsUrl}/pgstats/stats/geom-in-circle-counter";

        $url = "{$baseUrl}?type={$type}" . ($layers == null ? '' : "&layers=$layers");
        $http = Http::post($url, $validated);
        return $this->handleHttpRequest(
            $http,
            function($data) use ($validated){
                if (isset($validated['layer'])) {
                    $restData = $this->getRestDataFromGeoStatsInLayer($data["data"]);
                    return ["data" => $restData];
                } else {
                    return $data;
                }

            },
            function() {
                return $this->returnBadRequest();
            }
        );
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
            $params = [
                'request' => 'GetFeature',
                'typeNames' => $typeName,
                'count' => $perPage,
                'startIndex' => $startIndex
            ];
            $pkCol = ResourceBaseRepository::instance()->getPkColumnName($typeName);
            if (isset($pkCol)) {
                $params['sortBy'] = "$pkCol D";
            }

            $url = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->addParams($params)->url();

            $cql_filter = $validated['cql_filter']?? '';
            if (!empty($cql_filter)) {
                $url .= "&cql_filter={$cql_filter}";
            }

            $response =  Http::get($url);
            return $this->handleHttpRequest($response,
                // success callback
                function($data) use($typeName, $page, $perPage) {
                    $apiUrl = "{$this->geoRestUrl}/{$typeName}";
                    $resData = $this->convertGeoJsonToRestifyResponse($typeName, $apiUrl, $data, $page, $perPage);
                    return $resData;
                },
                // fail callback
                function() use($typeName, $page, $perPage) {
                    $apiUrl = "{$this->geoRestUrl}/{$typeName}";
                    
                    $resData = $this->convertGeoJsonToRestifyResponse($typeName, $apiUrl, [], $page, $perPage);
                    return $resData;
                }
            );
        });
    }

    public function search(Request $request, $typeName) {
        return $this->list($request, $typeName);
    }

    public function show(Request $request, $typeName, $fid) {
        try {
            $data = GeoFeatureRepositoryFacade::get($typeName, $fid);
            return $data;
        } catch (Exception $ex) {
            return (object)[];
        }
    }

    public function update(Request $request, $typeName, $fid) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use ($request, $typeName, $fid) {
            $data = $request->post();
            $data = $this->removePrimaryKey($data);
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
                        return $this->convertToRestifyCreateSuccessResponse($typeName, $fid, $data);
                    }
                } catch (Exception $ex) {
                    return $this->returnBadRequest();
                }
            });
        });
    }

    public function store(Request $request, $typeName) {
        try {
           return GeoFeatureRepositoryFacade::store($typeName, $request->post());
        } catch (Exception $ex) {
            return $this->returnBadRequest();
        }
    }

    public function delete(Request $request, $typeName, $fid) {
        return $this->actionVerifyGeonodeToken(function($accessToken) use ($request, $typeName, $fid) {
            ObjectsRecoveryRepositoryFacade::createRecoveryFromGeoDbFeature($typeName, $fid);
            $xml = WfsTransaction::build($typeName, $fid)->addDelete()->xml();
            $apiUrl = GeoServerUrlBuilder::buildWithAccessToken($accessToken)->url();
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

    public function getters(Request $request, $typeName, $getter) {
        return $this->actionVerifyGeonodeToken(function ($accessToken) use($request, $typeName, $getter) {
            return match ($getter) {
                'attribute_set' => $this->gettersAttributeSet($typeName),
                'trash' => $this->gettersTrash($typeName),
                default => $this->returnBadRequest()
            };
        });
    }

    public function actions(Request $request, $typeName, $action) {
        return $this->actionVerifyGeonodeToken(function ($accessToken) use($request, $typeName, $action) {
            return match ($action) {
                // ?id=objectRecoveryId
                'restore' => $this->gettersTrashRestore(),
                default => $this->returnBadRequest()
            };
        });
    }

    public function gettersAttributeSet($typeName) {
        $sql = <<<EOD
            SELECT attribute, description, attribute_label, attribute_type, visible, display_order, featureinfo_type
            FROM public.layers_attribute left join layers_layer on layers_layer.resourcebase_ptr_id = layers_attribute.layer_id
            WHERE typename = ? order by display_order
        EOD;
        $rows = $this->getDbConnection()->select($sql, [$typeName]);
        return [
            'data' => $rows
        ];
    }

    public function gettersTrash($typeName) {
        $data = ObjectsRecoveryRepositoryFacade::list($typeName);
        return [
            'data' => $data
        ];
    }

    public function gettersTrashRestore() {
        $data = request()->all();
        $id = isset($data['id']) ? $data['id'] : null;
        if ($id != null) {
            ObjectsRecoveryRepositoryFacade::restoreRecoveryToGeoDbFeature($id);
            return $this->returnOK();
        } else {
            dd($id);
            return $this->returnBadRequest();
        }
    }
}
