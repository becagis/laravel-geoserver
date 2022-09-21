<?php
namespace BecaGIS\LaravelGeoserver\Http\Traits;

use Symfony\Component\HttpFoundation\Response;

trait ActionReturnStatusTrait { 
    protected function returnCreated() {
        return response()->noContent(Response::HTTP_CREATED);
    }    

    protected function returnBadRequest() {
        return response()->noContent(Response::HTTP_BAD_REQUEST);
    }

    protected function returnNoContent() {
        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}