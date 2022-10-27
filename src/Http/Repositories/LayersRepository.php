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

    public function getGeomColsOfTable($tablename) {
        try {
            $sql = <<<EOD
            select column_name 
            from information_schema.columns 
            where udt_name = 'geometry' and table_name = :tablename limit 1
            EOD;

            $rows = $this->getDbShpConnection()->select($sql, [$tablename]);
            return $rows[0]->column_name;
        } catch (Exception $ex) {
            return 'the_geom';
        }
    }

    public function getLayerExtentGeoJson($typeName) {
        try {
            $tables = WfsRepository::instance()->getTableNamesByFeatureTypes([$typeName]);
            $tablename = $tables[0];

            $geomCol = $this->getGeomColsOfTable($tablename);

            $sql = <<<EOD
                select st_asgeojson(
                            st_envelope(
                                st_extent($geomCol)
                            )) as geojson
                from $tablename
            EOD;
            $rows = $this->getDbShpConnection()->select($sql);
            $geojson = $rows[0]->geojson;

            $this->getDbPSQL()->delete("delete from geonode_layer_extent where typename = :typeName", [$typeName]);
            $this->getDbPSQL()->insert("insert into geonode_layer_extent(typename, the_geom) values (:typeName, st_geomfromgeojson(:geojson))", [$typeName, $geojson]);

            return json_decode($geojson);
        } catch (Exception $ex) {
            return null;
        }    
    }

    public function updateLayersExtent($typeNames) {
        try {
            $listTypeNames = explode(',', $typeNames);
            foreach ($listTypeNames as $typeName) {
                $this->getLayerExtentGeoJson($typeName);
            }
            return $this->getLayersExtentGeoJson($typeNames);
        } catch (Exception $ex) {
            return null;
        }
    }

    public function getLayersExtentGeoJson($typeNames) {
        try {
            $sql = <<<EOD
                select st_asgeojson(st_envelope(st_extent(the_geom))) as geojson from geonode_layer_extent where :typeNames ilike ('%' || typename || ',%')
            EOD;
            $rows = $this->getDbPSQL()->select($sql, [$typeNames.',']);
            return json_decode($rows[0]->geojson);
        } catch (Exception $ex) {
            return null;
        }
    }
}