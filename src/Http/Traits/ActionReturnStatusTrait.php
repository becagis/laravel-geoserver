<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use Symfony\Component\HttpFoundation\Response;

trait ActionReturnStatusTrait { 
    protected function returnCreated() {
        return response()->noContent(Response::HTTP_CREATED);
    }    

    protected function returnBadRequest($message = "Thao tác bị từ chối") {
        return response()->json(["message" => $message, "status" => false], Response::HTTP_FOUND);
    }

    protected function returnNoContent() {
        return response()->noContent(Response::HTTP_NO_CONTENT);
    }

    protected function returnOK() {
        return response()->noContent(Response::HTTP_OK);
    }
}