<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\District;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;
use Throwable;

class LocationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function addLocation(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $this->validate($request, [
            'district' => "bail|required|regex:/^[a-zA-Z0-9-,\s]+$/|max:50",
            'city' => "bail|regex:/^[a-zA-Z0-9-,\s]+$/|max:50",
        ]);
        try {
            if ($request->district) {
                $request["district"] = removeAllowedSpecialCharactersAtStatAndEnd($request->district);
            }
            if ($request->city) {
                $request["city"] = removeAllowedSpecialCharactersAtStatAndEnd($request->city);
            }
            if (!self::isDistrictExists(generateSlug($request->district))) { //if district not exists
                if (!$request->city) { //if city empty
                    self::saveDistrict($request->district, generateSlug($request->district));
                    return response()->json([
                        "message" => "Location added",
                    ], config("constants.SERVER_STATUS_CODES.CREATED"));
                } else { //if city not empty
                    if (!self::isCityExists(generateSlug($request->city))) { //if city not exists
                        $district_id = self::saveDistrict($request->district, generateSlug($request->district));
                        self::saveCity($district_id, $request->city, generateSlug($request->city));
                        return response()->json([
                            "message" => "Location added",
                        ], config("constants.SERVER_STATUS_CODES.CREATED"));
                    } else { // if city exists
                        return response()->json([
                            "error" => "City already exists",
                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                    }
                }
            } else { //if district exists
                if (!$request->city) { // if city empty
                    return response()->json([
                        "error" => "District already exists",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                } else { //if city not empty
                    if (!self::isCityExists(generateSlug($request->city))) { // if city not exists
                        self::saveCity(self::getDistrictIdByDistrictValue(generateSlug($request->district)), $request->city, generateSlug($request->city));
                        return response()->json([
                            "message" => "Location added",
                        ], config("constants.SERVER_STATUS_CODES.CREATED"));
                    } else { // if city exists
                        return response()->json([
                            "error" => "City already exists",
                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                    }
                }
            }
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "error" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public static function isDistrictExists($districtValue)
    {
        return District::where(["value" => $districtValue])->exists();
    }

    public static function saveDistrict($text, $value)
    {
        $district = new District();
        $district->country_id = config("constants.DEFAULTS.COUNTRY_ID");
        $district->text = $text;
        $district->value = $value;
        $district->save();
        return $district->id;
    }

    public static function isCityExists($cityValue)
    {
        return City::where(["value" => $cityValue])->exists();
    }

    public static function saveCity($districtId, $text, $value)
    {
        $city = new City();
        $city->district_id = $districtId;
        $city->text = $text;
        $city->value = $value;
        $city->save();
    }

    public static function getDistrictIdByDistrictValue($districtValue)
    {
        return District::select("id")->where(["value" => $districtValue])->first()->id;
    }

    public function getLocationById(Request $request)
    {
        $this->validate($request, [
            'locationId' => "bail|required|regex:/^[0-9]+$/",
            'searchIn' => "bail|required|" . Rule::in(
                    [
                        config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_DISTRICT"),
                        config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_CITY")
                    ]
                ),
        ]);
        try {
            if ($request->searchIn === config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_DISTRICT")) {
                $district = self::getDistrictById($request->locationId);
                if (!is_null($district)) {
                    return response()->json([
                        "district" => $district->text,
                        "city" => "",
                    ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                } else {
                    return response()->json([
                        "message" => "Location not found",
                    ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
                }
            } else {
                $districtAndCity = self::getDistrictAndCityByCityId($request->locationId);
                if (!is_null($districtAndCity)) {
                    return response()->json([
                        "district" => $districtAndCity->district,
                        "city" => $districtAndCity->city,
                    ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                } else {
                    return response()->json([
                        "message" => "Location not found",
                    ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
                }
            }
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                "error" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public static function getDistrictById($districtId)
    {
        return District::select("text")->find($districtId);
    }

    public static function getDistrictAndCityByCityId($cityId)
    {
        return DB::table('cities')
            ->rightJoin('districts', 'cities.district_id', '=', 'districts.id')
            ->select('districts.text as district', 'cities.text as city')
            ->where(['cities.id' => $cityId])
            ->first();
    }

    public function viewAllLocations()
    {
        try {
            $locations = DB::table('districts')
                ->leftJoin('cities', 'districts.id', '=', 'cities.district_id')
                ->select('districts.id as district_id', 'districts.text as district', 'cities.id as city_id', 'cities.text as city')
                ->get();
            if ($locations->count()) {
                return response()->json([
                    "location_list" => $locations
                ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
            } else {
                return response()->json([
                    "message" => "Location list is empty",
                ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
            }
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "error" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function updateLocation(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $this->validate($request, [
            "locationId" => "bail|required|regex:/^[0-9]+$/",
            "updateModel" => "bail|required|" . Rule::in(
                    [
                        config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_DISTRICT"),
                        config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_CITY")
                    ]
                ),
            "value" => "bail|required|regex:/^[a-zA-Z0-9-,\s]+$/|max:50",
        ]);
        try {
            $request->value = removeAllowedSpecialCharactersAtStatAndEnd($request->value);
            if ($request->updateModel === config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_DISTRICT")) {
                if (self::isDistrictIdExists($request->locationId)) {
                    if (!self::isUpdatingDistrictExistsForAnotherDistrictId($request->locationId, generateSlug($request->value))) {
                        self::updateDistrict($request->locationId, $request->value, generateSlug($request->value));
                        return response()->json([
                            "message" => "Location Updated"
                        ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                    } else {
                        return response()->json([
                            "error" => "District already exists",
                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                    }
                } else {
                    return response()->json([
                        "error" => "The location id is invalid",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            } else {
                if (self::isCityIdExists($request->locationId)) {
                    if (!self::isUpdatingCityExistsForAnotherCityId($request->locationId, generateSlug($request->value))) {
                        self::updateCity($request->locationId, $request->value, generateSlug($request->value));
                        return response()->json([
                            "message" => "Location updated"
                        ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                    } else {
                        return response()->json([
                            "error" => "City already exists",
                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                    }
                } else {
                    return response()->json([
                        "error" => "The location id is invalid",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            }
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                "error" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public static function isDistrictIdExists(int $districtId)
    {
        return District::where(["id" => $districtId])->exists();
    }

    public static function isUpdatingDistrictExistsForAnotherDistrictId(int $updatingDistrictId, string $updatingValue)
    {
        $district = District::select("id")->where(["value" => $updatingValue])->first();
        if (!is_null($district)) {
            if ($district->id === $updatingDistrictId) {
                return false;
            }
            return true;
        }
        return false;
    }

    public static function updateDistrict(int $districtId, string $districtText, string $districtValue)
    {
        $district = District::find($districtId);
        $district->text = $districtText;
        $district->value = $districtValue;
        $district->save();
    }

    public static function isCityIdExists(int $cityId)
    {
        return City::where(["id" => $cityId])->exists();
    }

    public static function isUpdatingCityExistsForAnotherCityId(int $updatingCityId, string $updatingValue)
    {
        $city = City::select("id")->where(["value" => $updatingValue])->first();
        if (!is_null($city)) {
            if ($city->id === $updatingCityId) {
                return false;
            }
            return true;
        }
        return false;
    }

    public static function updateCity(int $cityId, string $cityText, string $cityValue)
    {
        $city = City::find($cityId);
        $city->text = $cityText;
        $city->value = $cityValue;
        $city->save();
    }

    public function deleteLocation(Request $request)
    {
        $this->validate($request, [
            "locationId" => "bail|required|regex:/^[0-9]+$/",
            "deleteModel" => "bail|required|" . Rule::in(
                    [
                        config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_DISTRICT"),
                        config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_CITY")
                    ]
                ),
        ]);
        try {
            if ($request->deleteModel === config("constants.SEARCH_FILTERS.LOCATION_SEARCH.BY_DISTRICT")) {
                if (self::isDistrictIdExists($request->locationId)) {
                    if (!self::isDistrictReferencedToACity($request->locationId) && !self::isDistrictAssociatedWithAnUserAccount($request->locationId)) {
                        self::deleteDistrict($request->locationId);
                        return response()->json([
                            "message" => "Location deleted"
                        ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                    } else {
                        return response()->json([
                            "error" => "Can't delete the location, because this location is associated with an user account or it is referenced to a city",
                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                    }
                } else {
                    return response()->json([
                        "error" => "The location id is invalid",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            } else {
                if (self::isCityIdExists($request->locationId)) {
                    if (!self::isCityAssociatedWithAnUserAccount($request->locationId)) {
                        self::deleteCity($request->locationId);
                        return response()->json([
                            "message" => "Location deleted"
                        ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                    } else {
                        return response()->json([
                            "error" => "Can't delete the location, because this location is associated with an user account",
                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                    }
                } else {
                    return response()->json([
                        "error" => "The location id is invalid",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            }
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                "error" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public static function isDistrictReferencedToACity($districtId)
    {
        return City::where(["district_id" => $districtId])->exists();
    }

    public static function isDistrictAssociatedWithAnUserAccount($districtId)
    {
        return User::where(["district_id" => $districtId])->exists();
    }

    public static function deleteDistrict($districtId)
    {
        return District::destroy($districtId);
    }

    public static function isCityAssociatedWithAnUserAccount($cityId)
    {
        return User::where(["city_id" => $cityId])->exists();
    }

    public static function deleteCity($cityId)
    {
        return City::destroy($cityId);
    }
}
