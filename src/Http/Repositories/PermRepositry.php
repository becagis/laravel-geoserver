<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Traits\ActionVerifyGeonodeTokenTrait;
use BecaGIS\LaravelGeoserver\Http\Traits\GeonodeDbTrait;
use DateTime;
use Exception;
use TungTT\LaravelGeoNode\Facades\GeoNode;

class PermRepositry {
    use GeonodeDbTrait, ActionVerifyGeonodeTokenTrait;
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
            return [];
        } else {
            $res = [];
            $listTypeName = is_string($listTypeName) ? explode(',', $listTypeName) : $listTypeName;
            foreach ($listTypeName as $typename) {
                $lower = $typename;
                if (in_array($typename, $permLayers) || in_array("geonode:$lower", $permLayers)) {
                    array_push($res, $typename);
                }
            }
            return $res;
        }
    }

    public function getListLayersTypeNameCanAccess($actorId, $actorType, $perms) {
        $listLayers = $this->getLayersActorCanAccess($actorId, $actorType, $perms);
        $permLayers = array_column($listLayers, 'typename');
        return isset($permLayers) ? array_unique($permLayers) : [];
    }

    public function isAdmin($actorId) {
        try {
            $sql = <<<EOD
            select * from people_profile where id = :actorId and is_superuser
            EOD;
            $rows = $this->getDbConnection()->select($sql, [$actorId]);
            return isset($rows) && sizeof($rows);
        } catch (Exception $ex) {
            return false;
        }
    }

    public function getPermsOfAdminOrOwner() {
        return [
            'view_resourcebase',
            'change_resourcebase',
            'delete_resourcebase',
            'change_resourcebase_permissions',
            'change_layer_data'
        ];
    }

    // actoryType: group/user, $unitType: layer/map
    public function getActorPermsOnUnit($actorId, $actorType, $unitType) {
        // $sql = <<<EOD
        //     select owner_id, layers_layer.resourcebase_ptr_id as layer_id,layers_layer.typename as layer_typename, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
        //     from guardian_userobjectpermission
        //     left join base_resourcebase on base_resourcebase.id::text = guardian_userobjectpermission.object_pk
        //     left join layers_layer on layers_layer.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
        //     left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
        //     left join guardian_groupobjectpermission 
        //         ON (guardian_groupobjectpermission.permission_id = guardian_userobjectpermission.permission_id and guardian_groupobjectpermission.object_pk = guardian_userobjectpermission.object_pk)
        //     left join auth_group ON auth_group.id = guardian_groupobjectpermission.group_id and auth_group."name" = 'anonymous'
        //     left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
        //     left join django_content_type ON django_content_type.id = auth_permission.content_type_id
            
        //     where (user_id = ? or user_id = -1 or auth_group."name" = 'anonymous' or 1000 = ?) and model in ('resourcebase', ?)
        // EOD;
        $sql = <<<EOD
                select owner_id, layers_layer.resourcebase_ptr_id as layer_id,layers_layer.typename as layer_typename, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
                from (
                    select * from guardian_userobjectpermission where (user_id = :actorId or user_id = -1  or 1000 = :actorId)
                ) guardian_userobjectpermission
                left join base_resourcebase on base_resourcebase.id::text = guardian_userobjectpermission.object_pk
                left join layers_layer on layers_layer.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
                left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
                left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
                left join django_content_type ON django_content_type.id = auth_permission.content_type_id
                
                where model in ('resourcebase', :unitType)
                
                union
                
                select owner_id, layers_layer.resourcebase_ptr_id as layer_id,layers_layer.typename as layer_typename, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
                from (
                    select * from guardian_groupobjectpermission 
                    where group_id in (select group_id from people_profile_groups where profile_id = :actorId)
                        or group_id in (select id from auth_group where "name" = 'anonymous')
                ) guardian_groupobjectpermission
                left join base_resourcebase on base_resourcebase.id::text = guardian_groupobjectpermission.object_pk
                left join layers_layer on layers_layer.resourcebase_ptr_id::text = guardian_groupobjectpermission.object_pk
                left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_groupobjectpermission.object_pk
                left join auth_permission ON auth_permission.id = guardian_groupobjectpermission.permission_id
                left join django_content_type ON django_content_type.id = auth_permission.content_type_id
                
                where   model in ('resourcebase', :unitType)
        EOD;
        $rows = $this->getDbConnection()->select($sql, [$actorId, $unitType]);
        $result = [];
        $pk = $unitType . "_id";
        $typenameCol = $unitType . "_typename";
        $isAdmin =  $this->isAdmin($actorId);
        foreach ($rows as $row) {
            $id = $row->$pk;
            if (isset($id) && !empty($id)) {
                if (!isset($result[$id])) {
                    $result[$id] = [
                        'resourcesbase_ptr_id' => $id,
                        'typename' => $row->$typenameCol,
                        'type' => $unitType,
                        'perms' => []
                    ];
                }
                if ($row->owner_id == $actorId || $isAdmin) {
                    $result[$id]['perms'] = array_merge($result[$id]['perms'], $this->getPermsOfAdminOrOwner());
                } else {
                    array_push($result[$id]['perms'], $row->codename);
                }
                $result[$id]['perms'] = array_unique($result[$id]['perms']);
            }
        }
        return $result;
    }

        // actoryType: group/user, $unitType: layer/map
    public function getActorPermsOnLayerUnit($actorId, $actorType, $typeName) {
            // $sql = <<<EOD
            //     select owner_id, layers_layer.resourcebase_ptr_id as layer_id,layers_layer.typename as layer_typename, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
            //     from guardian_userobjectpermission
            //     left join base_resourcebase on base_resourcebase.id::text = guardian_userobjectpermission.object_pk
            //     left join layers_layer on layers_layer.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
            //     left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
            //     left join guardian_groupobjectpermission 
            //         ON (guardian_groupobjectpermission.permission_id = guardian_userobjectpermission.permission_id and guardian_groupobjectpermission.object_pk = guardian_userobjectpermission.object_pk)
            //     left join auth_group ON auth_group.id = guardian_groupobjectpermission.group_id and auth_group."name" = 'anonymous'
            //     left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
            //     left join django_content_type ON django_content_type.id = auth_permission.content_type_id
                
            //     where (user_id = ? or user_id = -1 or auth_group."name" = 'anonymous' or 1000 = ?) and model in ('resourcebase', ?)
            // EOD;
            $unitType = 'layer';
            $sql = <<<EOD
                    select owner_id, layers_layer.resourcebase_ptr_id as layer_id,layers_layer.typename as layer_typename, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
                    from (
                        select * from guardian_userobjectpermission where (user_id = :actorId or user_id = -1  or 1000 = :actorId)
                    ) guardian_userobjectpermission
                    left join base_resourcebase on base_resourcebase.id::text = guardian_userobjectpermission.object_pk
                    left join layers_layer on layers_layer.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
                    left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
                    left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
                    left join django_content_type ON django_content_type.id = auth_permission.content_type_id
                    
                    where model in ('resourcebase', :unitType) and layers_layer.typename = :typeName
                    
                    union
                    
                    select owner_id, layers_layer.resourcebase_ptr_id as layer_id,layers_layer.typename as layer_typename, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
                    from (
                        select * from guardian_groupobjectpermission 
                        where group_id in (select group_id from people_profile_groups where profile_id = :actorId)
                            or group_id in (select id from auth_group where "name" = 'anonymous')
                    ) guardian_groupobjectpermission
                    left join base_resourcebase on base_resourcebase.id::text = guardian_groupobjectpermission.object_pk
                    left join layers_layer on layers_layer.resourcebase_ptr_id::text = guardian_groupobjectpermission.object_pk
                    left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_groupobjectpermission.object_pk
                    left join auth_permission ON auth_permission.id = guardian_groupobjectpermission.permission_id
                    left join django_content_type ON django_content_type.id = auth_permission.content_type_id
                    
                    where   model in ('resourcebase', :unitType) and layers_layer.typename = :typeName
            EOD;
            $rows = $this->getDbConnection()->select($sql, [$actorId, $unitType, $typeName]);
            $result = [];
            $pk = $unitType . "_id";
            $typenameCol = $unitType . "_typename";
            $isAdmin =  $this->isAdmin($actorId);
            foreach ($rows as $row) {
                $id = $row->$pk;
                if (isset($id) && !empty($id)) {
                    if (!isset($result[$id])) {
                        $result[$id] = [
                            'resourcesbase_ptr_id' => $id,
                            'typename' => $row->$typenameCol,
                            'type' => $unitType,
                            'perms' => []
                        ];
                    }
                    if ($row->owner_id == $actorId || $isAdmin) {
                        $result[$id]['perms'] = array_merge($result[$id]['perms'], $this->getPermsOfAdminOrOwner());
                    } else {
                        array_push($result[$id]['perms'], $row->codename);
                    }
                    $result[$id]['perms'] = array_unique($result[$id]['perms']);
                }
            }
            return $result;
        }

    // resourceBasePtrId: mapId in geoportal_data.maps_map.
    public function getCurrentPermsOnMap($resourceBasePtrId) {
        // $sql = <<<EOD
        //     select base_resourcebase.owner_id, maps_map.resourcebase_ptr_id as layer_id, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
        //     from guardian_userobjectpermission
        //     left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
        //     left join base_resourcebase on base_resourcebase.id = maps_map.resourcebase_ptr_id
        //     left join guardian_groupobjectpermission 
        //         ON (guardian_groupobjectpermission.permission_id = guardian_userobjectpermission.permission_id and guardian_groupobjectpermission.object_pk = guardian_userobjectpermission.object_pk)
        //     left join auth_group ON auth_group.id = guardian_groupobjectpermission.group_id and auth_group."name" = 'anonymous'
        //     left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
        //     left join django_content_type ON django_content_type.id = auth_permission.content_type_id
            
        //     where base_resourcebase.id = :resourceBasePtrId and (owner_id = :actorId or user_id = :actorId or user_id = -1 or auth_group."name" = 'anonymous' or 1000 = :actorId) and model in ('resourcebase', 'map')
        // EOD;
        $sql = <<<EOD
            select base_resourcebase.owner_id, maps_map.resourcebase_ptr_id as layer_id, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
            from guardian_userobjectpermission
            left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_userobjectpermission.object_pk
            left join base_resourcebase on base_resourcebase.id = maps_map.resourcebase_ptr_id
            left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
            left join django_content_type ON django_content_type.id = auth_permission.content_type_id
            where base_resourcebase.id = :resourceBasePtrId 
                    and (owner_id = :actorId or user_id = :actorId or user_id = -1 or 1000 = :actorId) 
                    and model in ('resourcebase', 'map')
            union
            select base_resourcebase.owner_id, maps_map.resourcebase_ptr_id as layer_id, codename, model, maps_map.resourcebase_ptr_id as map_id, maps_map.title_en  as map_typename
            from (
                    select * from guardian_groupobjectpermission 
                    where group_id in (select group_id from people_profile_groups where profile_id = :actorId)
                        or group_id in (select id from auth_group where "name" = 'anonymous')
                ) guardian_groupobjectpermission
            left join maps_map on maps_map.resourcebase_ptr_id::text = guardian_groupobjectpermission.object_pk
            left join base_resourcebase on base_resourcebase.id = maps_map.resourcebase_ptr_id
            left join auth_permission ON auth_permission.id = guardian_groupobjectpermission.permission_id
            left join django_content_type ON django_content_type.id = auth_permission.content_type_id
            
            where base_resourcebase.id = :resourceBasePtrId
            and model in ('resourcebase', 'map')
        EOD;
        $actorId = $this->getUserProviderId();
        $isAdmin = $this->isAdmin($actorId);
        $rows = $this->getDbConnection()->select($sql, [$resourceBasePtrId, $actorId]);
        $result = [];
        foreach ($rows as $row) {
            if ($row->owner_id == $actorId || $isAdmin) {
                $result = array_merge($result, $this->getPermsOfAdminOrOwner());
            } else {
                array_push($result, $row->codename);
            }
        }
        $result = array_unique($result);
        return $result;
    }

    public function getLayersActorCanAccess($actorId, $actorType, $perms) {
        $sql = match ($actorType) {
            //PermRepositry::ActorTypeGroup => $this->getSQLLayersByGroup(),
            PermRepositry::ActorTypeUser => $this->getSQLLayersByUser(),
            default => []
        };
        $strPerms = "'" . implode("','", $perms) . "'";
        $sqlPerms = isset($perms) && sizeof($perms) > 0 ? " and codename in ($strPerms) " : "";//$this->getPermsQuery($perms);
        //$params = $this->getPermsParams($perms);
        $sql = "$sql $sqlPerms";
        $rows = $this->getDbConnection()->select($sql, [$actorId]);
        return $rows;
    }

    public function getLayerActorCanAccess($actorId, $actorType, $perms, $layerType) {
        $sql = match ($actorType) {
            //PermRepositry::ActorTypeGroup => $this->getSQLLayersByGroup(),
            PermRepositry::ActorTypeUser => $this->getSQLLayerByUser(),
            default => []
        };
        $sqlPerms = $this->getPermsQuery($perms);
        $params = $this->getPermsParams($perms);
        $rows = $this->getDbConnection()->select("$sql and $sqlPerms", [$layerType, $actorId, $actorId, $actorId, ...$params]);
        return $rows;
    }

    // public function checkActorCanActionOnLayer($actorId, $actorType, $perms, $layerTypeName, $perm) {
    //     $providerId = $this->getUserProviderId();
    //     $layers = PermRepositry::instance()->getLayerActorCanAccess($providerId, 'user', ['view_resourcebase'], $typeName);
    //     $perms = array_column($layers, 'perm');
    // }

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
            //PermRepositry::ActorTypeGroup => $this->getSQLMapsByGroup(),
            PermRepositry::ActorTypeUser => $this->getSQLMapsByUser(),
            default => []
        };
        $actorId = isset($actorId) ? $actorId : '-1';
        $strPerms = "'" . implode("','", $perms) . "'";
        $sqlPerms = isset($perms) && sizeof($perms) > 0 ? " and codename in ($strPerms) " : "";//$this->getPermsQuery($perms);
        //$params = $this->getPermsParams($perms);
        $sql = "$sql $sqlPerms";
        $rows = $this->getDbConnection()->select($sql, [$actorId]);
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
        // return <<<EOD
        //     select auth_group.name, user_id, username, codename as perm, layers_layer.title_en, layers_layer.resourcebase_ptr_id, layers_layer.typename 
        //     from layers_layer
        //     left join guardian_userobjectpermission on guardian_userobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
        //     left join guardian_groupobjectpermission on guardian_groupobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
        //     left join auth_group ON auth_group.id = guardian_groupobjectpermission.group_id and auth_group."name" = 'anonymous'
        //     left join django_content_type ON django_content_type.id = guardian_userobjectpermission.content_type_id
        //     left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
        //     left join people_profile ON people_profile.id = guardian_userobjectpermission.user_id
        //     left join base_resourcebase on base_resourcebase.id = layers_layer.resourcebase_ptr_id
        //     where (base_resourcebase.owner_id = ?) 
        //         or (? in (select id from people_profile where  is_superuser))
        //         or (guardian_userobjectpermission.user_id = ? or guardian_userobjectpermission.user_id = -1 or auth_group."name" = 'anonymous') 
        // EOD;
        return <<<EOD
            select '' as name, user_id, username, codename as perm, layers_layer.title_en, layers_layer.resourcebase_ptr_id, layers_layer.typename 
            from layers_layer
            left join guardian_userobjectpermission on guardian_userobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
            --left join guardian_groupobjectpermission on guardian_groupobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
            --left join auth_group ON auth_group.id = guardian_groupobjectpermission.group_id and auth_group."name" = 'anonymous'
            left join django_content_type ON django_content_type.id = guardian_userobjectpermission.content_type_id
            left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
            left join people_profile ON people_profile.id = guardian_userobjectpermission.user_id
            left join base_resourcebase on base_resourcebase.id = layers_layer.resourcebase_ptr_id
            where (base_resourcebase.owner_id = :actorId) 
                or (1006 in (select id from people_profile where  is_superuser))
                or (guardian_userobjectpermission.user_id = :actorId or guardian_userobjectpermission.user_id = -1) 
            UNION
            select auth_group.name, user_id, username, codename as perm, layers_layer.title_en, layers_layer.resourcebase_ptr_id, layers_layer.typename 
            from layers_layer
            left join guardian_userobjectpermission on guardian_userobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
            inner join 
            (
                    select * from guardian_groupobjectpermission 
                    where group_id in (select group_id from people_profile_groups where profile_id = :actorId)
                        or group_id in (select id from auth_group where "name" = 'anonymous')
                ) guardian_groupobjectpermission
            on guardian_groupobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
            left join auth_group ON auth_group.id = guardian_groupobjectpermission.group_id
            left join django_content_type ON django_content_type.id = guardian_userobjectpermission.content_type_id
            left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
            left join people_profile ON people_profile.id = guardian_userobjectpermission.user_id
            left join base_resourcebase on base_resourcebase.id = layers_layer.resourcebase_ptr_id
        EOD;
    }

    protected function getSQLLayerByUser() {
        return <<<EOD
            select auth_group.name, user_id, username, codename as perm, layers_layer.title_en, layers_layer.resourcebase_ptr_id, layers_layer.typename 
            from (select * from layers_layer where typename = ?) layers_layer
            left join guardian_userobjectpermission on guardian_userobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
            left join guardian_groupobjectpermission on guardian_groupobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
            left join auth_group ON auth_group.id = guardian_groupobjectpermission.group_id and auth_group."name" = 'anonymous'
            left join django_content_type ON django_content_type.id = guardian_userobjectpermission.content_type_id
            left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
            left join people_profile ON people_profile.id = guardian_userobjectpermission.user_id
            left join base_resourcebase on base_resourcebase.id = layers_layer.resourcebase_ptr_id
            where (base_resourcebase.owner_id = ?) 
                or (? in (select id from people_profile where  is_superuser))
                or (guardian_userobjectpermission.user_id = ? or guardian_userobjectpermission.user_id = -1 or auth_group."name" = 'anonymous') 
        EOD;
    }

    protected function getSQLMapsByGroup() {

    }

    protected function getSQLMapsByUser() {
        return <<<EOD
        select user_id, username, codename as perm, maps_map.title_en, maps_map.resourcebase_ptr_id 
        from maps_map
        left join base_resourcebase on base_resourcebase.id = maps_map.resourcebase_ptr_id
        left join guardian_userobjectpermission on guardian_userobjectpermission.object_pk::integer = maps_map.resourcebase_ptr_id
        left join django_content_type ON django_content_type.id = guardian_userobjectpermission.content_type_id
        left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
        left join people_profile ON people_profile.id = guardian_userobjectpermission.user_id
        where (base_resourcebase.owner_id = :actorId)
            or (? in (select id from people_profile where  is_superuser)) 
            or (guardian_userobjectpermission.user_id = :actorId or guardian_userobjectpermission.user_id = -1)
        union
        select user_id, username, codename as perm, maps_map.title_en, maps_map.resourcebase_ptr_id 
        from maps_map
        left join base_resourcebase on base_resourcebase.id = maps_map.resourcebase_ptr_id
        inner join 
        (
                select * from guardian_groupobjectpermission 
                where group_id in (select group_id from people_profile_groups where profile_id = :actorId)
                    or group_id in (select id from auth_group where "name" = 'anonymous')
            ) guardian_groupobjectpermission
        on guardian_groupobjectpermission.object_pk::integer = layers_layer.resourcebase_ptr_id
        left join auth_group ON auth_group.id = guardian_groupobjectpermission.group_id and auth_group."name" = 'anonymous'
        left join django_content_type ON django_content_type.id = guardian_userobjectpermission.content_type_id
        left join auth_permission ON auth_permission.id = guardian_userobjectpermission.permission_id
        left join people_profile ON people_profile.id = guardian_userobjectpermission.user_id
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