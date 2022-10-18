<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Models\ObjectsRecoveryModel;
use BecaGIS\LaravelGeoserver\Http\Repositories\Facades\GeoFeatureRepositoryFacade;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use DateTime;
use Exception;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class ObjectsRecoveryRepository {
    use GeonodeDbTrait;
    public function createRecoveryFromGeoDbFeature($typeName, $fid) {
        $feature = GeoFeatureRepositoryFacade::get($typeName, $fid);
        $id = gettype($feature) == 'object' ? $feature->id : $feature['id'];
        if (isset($id)) {
            try {
                extract($feature, EXTR_PREFIX_ALL, 'obj');
                $idSplit = explode('.', $obj_id);
                $username = GeoNode::user() != null ? GeoNode::user()->username : '';
                $data = [
                    'object_pk' => $idSplit[1],
                    'object_type' => $typeName,
                    'created_at' => new DateTime(),
                    'object_db' => $this->DbGeonodeData,
                    'created_by' => $username,
                    'data' => json_encode($obj_attributes),
                    'geom' => json_encode($this->getGeometry($obj_attributes)),
                    'status' => ObjectsRecoveryModel::$STATUS_INTRASH
                ];
                ObjectsRecoveryModel::create($data);
            } catch (Exception $ex) {
            }
        }
    }

    public function restoreRecoveryToGeoDbFeature($objectRecoveryId) {
        try  {
            $objRecovery = ObjectsRecoveryModel::find($objectRecoveryId);
            $typename = $objRecovery->object_type;
            $data = json_decode($objRecovery->data, true);
            GeoFeatureRepositoryFacade::store($typename, $data);

            $objRecovery->restored_at = new DateTime();
            $username = GeoNode::user() != null ? GeoNode::user()->username : '';
            $objRecovery->restored_by = $username;
            $objRecovery->status = ObjectsRecoveryModel::$STATUS_RESTORED;
            $objRecovery->save();
        } catch (Exception $ex) {
        }
    }

    public function list($typeName) {
        $sql = "select * from objects_recovery where object_type = ? and restored_at is null order by created_at desc";
        $rows = $this->getDbPSQL()->select($sql, [$typeName]);
        return $rows;
    }

    protected function getGeometry($attributes) {
        $geomFields = ['geom', 'geometry', 'the_geom'];
        foreach ($geomFields as $geomField) {
            if (isset($attributes[$geomField])) {
                return $attributes[$geomField];
            }
        }
        return '';
    }
}