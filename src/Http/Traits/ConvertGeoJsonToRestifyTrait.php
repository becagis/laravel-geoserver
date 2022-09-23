<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use TungTT\LaravelGeoNode\Facades\GeoNode as FacadesGeoNode;

trait ConvertGeoJsonToRestifyTrait {
    protected function convertGeoJsonToRestifyResponse($typeName, $apiUrl, $data, $page, $perPage) {
        return [
            'meta' => $this->getRestMeta($apiUrl, $data, $page, $perPage),
            'links' => $this->getRestLinks($apiUrl, $data, $page, $perPage),
            'data' => $this->getRestData($typeName, $data),
            'accessToken' => FacadesGeoNode::getAccessToken()
        ];
    }

    protected function convertToRestifyCreateSuccessResponse($typeName, $fid, $objData) {
        return [
                "data" => [
                    "id" => $fid,
                    "type" => $typeName,
                    "attributes" => $objData,
                    "meta" => []
                ]   
            ];  
    }

    protected function getRestMeta($apiUrl, $data, $page, $perPage) {
        $total = $data["totalFeatures"]?? 0;    
        $from = ($page - 1) * $perPage;
        return [
            "current_page" =>  $page,
            "from" =>  $from,
            "last_page" =>  round($total / $perPage),
            "path" =>  $apiUrl,
            "per_page" =>  $perPage,
            "to" =>  $from + $perPage,
            "total" => $total
        ];
    }

    protected function getRestLinks($apiUrl, $data, $page, $perPage) {
        $total = $data["totalFeatures"]?? 0;
        $last = round($total/$perPage);
        $next = $page < $last ? "{$apiUrl}?page=". ($page + 1) : null;
        $prev = $page > 1 ? "{$apiUrl}?page=". ($page - 1) : null;
        return [
            "first" => "{$apiUrl}?page=1",
            "last" => "{$apiUrl}?page={$last}",
            "prev" => $prev,
            "next" => $next
        ];
    }

    protected function getRestData($typeName, $data) {
        $result = [];
        $features = $data['features']?? [];
        foreach ($features as $feature) {
            $props = $feature['properties'];
            $props[$feature["geometry_name"]] = $feature["geometry"];
            array_push($result, [
                'id' => $feature['id'],
                'type' => $typeName,
                'attributes' => $props,
                'meta' => []
            ]);
        }
        return $result;
    }

    protected function getRestDataFromGeoStatsInLayer($data) {
        $result = [];
        foreach ($data as $arr) {
            foreach ($arr as $item) {
                
                array_push($result, ["name" => $item["name"], "attributes" => $item["properties"]]);
            }
        }
        return $result;
    }
}