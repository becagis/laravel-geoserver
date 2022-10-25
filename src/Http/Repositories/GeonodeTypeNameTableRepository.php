<?php 
namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Http\Models\GeonodeTypeNameTableModel;

class GeonodeTypeNameTableRepository {
    protected static $ins;
    protected $cacheMapFeatureTypeToTableName;

    public static function instance() {
        if (!isset(self::$ins)) {
            self::$ins = new GeonodeTypeNameTableRepository();
        } 
        return self::$ins;
    }

    public function getMapFeatureTypeTable() {
        $rows = GeonodeTypeNameTableModel::select()->get();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->typename] = $row->table;
        }
        return $result;
    }

    public function storeCache($typename, $table) {
        $obj = new GeonodeTypeNameTableModel();
        $obj->typename = $typename;
        $obj->table = $table;
        $obj->save();
    }
}