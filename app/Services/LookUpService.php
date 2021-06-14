<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Gender;

class LookUpService
{
    public static function countries()
    {
        return Country::select("id", "text", "value")->get();
    }

    public static function districts()
    {
        return District::select("id", "country_id", "text", "value")->get();
    }

    public static function cities(string $filter_id = null)
    {
        $cities = City::select("id", "district_id", "text", "value");
        return is_null($filter_id) ? $cities->get() : $cities->where(["district_id" => $filter_id])->get();
    }

    public static function genders()
    {
        return Gender::select("text", "value")->get();
    }
}
