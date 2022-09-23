<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use Exception;
use TungTT\LaravelGeoNode\Facades\GeoNode as FacadesGeoNode;

trait PermissionCheckTrait { 
    use GeonodeDbTrait;

    public function havePermission($permCodeName, $pk) {
        try {
            $userId = FacadesGeoNode::user()->provider_id;
            $sql = <<<EOD
                select user_id, codename, model, object_pk from guardian_userobjectpermission 
                left join auth_permission on auth_permission.id = guardian_userobjectpermission.permission_id
                left join django_content_type on django_content_type.id = guardian_userobjectpermission.content_type_id
                join layers_layer on layers_layer.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
                where user_id = ? and (object_pk = ? or typename = ?) and codename = ?
            EOD;
            $rows = $this->getDbConnection()->select($sql, [$userId, (string)$pk, (string)$pk, $permCodeName]);
            return sizeof($rows) > 0;
        } catch (Exception $ex) {
            return false;
        }
    } 
}