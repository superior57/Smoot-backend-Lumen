<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Services\LookUpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class LookUpController extends Controller
{
    public function getAllCountries()
    {
        try {
            return response()->json([
                "countries" => LookUpService::countries(),
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getAllDistricts()
    {
        try {
            return response()->json([
                "districts" => LookUpService::districts(),
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getCities(Request $request)
    {
        $this->validate($request, [
            "filter_value" => "regex:/^[a-z-0-9]+$/",
        ]);
        try {
            $filter_id = District::select("id")->where(["value" => $request->query("filter_value")])->first()->id;
            return response()->json([
                "cities" => LookUpService::cities($filter_id),
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getGenders()
    {
        try {
            return response()->json([
                "genders" => LookUpService::genders(),
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }
}
