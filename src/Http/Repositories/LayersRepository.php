<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Models\ObjectsRecoveryModel;
use BecaGIS\LaravelGeoserver\Http\Repositories\Facades\GeoFeatureRepositoryFacade;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use DateTime;
use Exception;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class LayersRepository {
    use GeonodeDbTrait;
    protected static $instance = null;

    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new LayersRepository();
        }
        return self::$instance;
    }

    /**
     * @param $typenames: [typename1, typename2,...]  
     * typename: geonode:baubang_thuadat
     * @return $filteredTypenames: [typename1Filter,...]
     */
    public function filterLayersExistByTypeName($typenames) {
        try {
            $paramString = $this->getParamsStringFromTypeNames($typenames);
            if (empty($paramString)) {
                return [];
            }
            $sql = <<< EOD
                    select typename
                    from (select unnest(ARRAY[{$paramString}]) as typename) typenames
                    where typename in (select typename from layers_layer)
            EOD;
    
            $rows = $this->getDbConnection()->select($sql, $typenames);
            if (!isset($rows) && empty($rows)) {
                return [];
            }
            return array_column($rows, 'typename');
        } catch (Exception $ex) {
            return $typenames;
        }
    }

    protected function getParamsStringFromTypeNames($typenames) {
        $resultArr = [];
        if (isset($typenames) && is_array($typenames)) {
            foreach ($typenames as $typenames) {
                array_push($resultArr, "?");
            }
        }
        return implode(',', $resultArr);;
    }
}