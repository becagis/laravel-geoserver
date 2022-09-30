<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use DateTime;
use Exception;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class PermRepositry {
    use GeonodeDbTrait;
    const ActorTypeGroup = 'group';
    const ActorTypeUser = 'user';

    protected static $instance;
    public static function instance() {
        if (!isset($instance)) {
            $instance = new PermRepositry();
        }
        return $instance;
    }

    // actorId -> provider_id/group_id, actorType: user, group
    // perms: ['', '', '']
    // listTypeName: ['layera', 'layerb']
    public function filterListLayerTypeNameCanAccess($actorId, $actorType, $perms, $listTypeName) {
        $listLayers = $this->getLayersActorCanAccess($actorId, $actorType, $perms);
        $permLayers = array_column($listLayers, 'typename');
        
        if (!isset($listTypeName) || empty($listTypeName)) {
            return $permLayers;
        } else {
            $res = [];
            $listTypeName = is_string($listTypeName) ? explode(',', $listTypeName) : $listTypeName;
            foreach ($listTypeName as $typename) {
                if (in_array($typename, $permLayers) || in_array("geonode:$typename", $permLayers)) {
                    array_push($res, $typename);
                }
            } 
            return $res;
        }
    }

    public function getLayersActorCanAccess($actorId, $actorType, $perms) {
        $sql = match ($actorType) {
            PermRepositry::ActorTypeGroup => $this->getSQLLayersByGroup(),
            PermRepositry::ActorTypeUser => $this->getSQLLayersByUser(),
            default => []
        };
        $sqlPerms = $this->getPermsQuery($perms);
        $params = $this->getPermsParams($perms);
        $rows = $this->getDbConnection()->select("$sql and $sqlPerms", [$actorId, ...$params]);
        return $rows;
    }

    public function filterListGeonodeMapIdCanAccess($actorId, $actorType, $perms, $listMapId) {
        $listMaps = $this->getMapsActorCanAccess($actorId, $actorType, $perms);
        $permMaps = array_column($listMaps, 'resourcebase_ptr_id');
        if (!isset($listTypeName) || empty($listTypeName)) {
            return $permMaps;
        } else {
            $res = [];
            foreach ($listMapId as $typename) {
                if (in_array($typename, $permMaps)) {
                    array_push($res, $typename);
                }
            } 
            return $res;
        }
    }

    public function getMapsActorCanAccess($actorId, $actorType, $perms) {
        $sql = match ($actorType) {
            PermRepositry::ActorTypeGroup => $this->getSQLMapsByGroup(),
            PermRepositry::ActorTypeUser => $this->getSQLMapsByUser(),
            default => []
        };
        $sqlPerms = $this->getPermsQuery($perms);
        $params = $this->getPermsParams($perms);
        $rows = $this->getDbConnection()->select("$sql and $sqlPerms", [$actorId, ...$params]);
        return $rows;
    }

    public function getSQLMapsActorCanAccess($actorId, $actorType, $perms) {
        return match ($actorType) {
            PermRepositry::ActorTypeGroup => $this->getSQLMapsByGroup(),
            PermRepositry::ActorTypeUser => $this->getSQLMapsByUser(),
            default => []
        };
    }

    protected function getSQLLayersByGroup() {

    }

    protected function getSQLLayersByUser() {
        return <<<EOD
        select user_id, username, codename as perm, layers_layer.title_en, layers_layer.resourcebase_ptr_id, layers_layer.typename from layers_layer
        left join guardian_userobjectpermission on guardian_userobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
        left join django_content_type ON django_content_type.id = guardian_userobjectpermission.content_type_id
        left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
        left join people_profile ON people_profile.id = guardian_userobjectpermission.user_id
        where (guardian_userobjectpermission.user_id = ? or guardian_userobjectpermission.user_id = -1) 
        EOD;
    }

    protected function getSQLMapsByGroup() {

    }

    protected function getSQLMapsByUser() {
        return <<<EOD
            select user_id, username, codename as perm, maps_map.title_en, maps_map.resourcebase_ptr_id from maps_map
            left join guardian_userobjectpermission on guardian_userobjectpermission.object_pk::integer = maps_map.resourcebase_ptr_id
            left join django_content_type ON django_content_type.id = guardian_userobjectpermission.content_type_id
            left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
            left join people_profile ON people_profile.id = guardian_userobjectpermission.user_id
            where (guardian_userobjectpermission.user_id = ? or guardian_userobjectpermission.user_id = -1) 
        EOD;
    }

    protected function getPermsQuery($perms) {
        $tmp = [];
        foreach ($perms as $perms) {
            array_push($tmp, '?'); 
        }
        $qParams = implode(',', $tmp);
        if (!empty($qParams)) {
            return " codename in ($qParams) ";
        } else {
            return " 1 = ? ";
        }
    }

    protected function getPermsParams($perms) {
        if (sizeof($perms) > 0) {
            return $perms;
        } else {
            return "1";
        }
    }
}