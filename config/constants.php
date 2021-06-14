<?php
return [
    "DICTIONARY" => [
        "EXCLUDED_WORDS" => [
            "FOR_FIRST_LETTER_CAPITALIZE" => [
                "iphone", "imac", "ipad", "ipod", "iwatch"
            ],
        ],
    ],
    "SERVER_STATUS_CODES" => [
        "SUCCESS" => 200,
        "CREATED" => 201,
        "UNAUTHORIZED" => 401,
        "NOT_FOUND" => 404,
        "UNPROCESSABLE_ENTITY" => 422,
        "INTERNAL_SERVER_ERROR" => 500,
    ],
    "SIGNUP_VERIFICATION_CODE_GENERATION_DIGITS" => 4,
    "MEDIA_STORAGE" => [
        "PUBLIC_KEY" => env("IMAGEKIT_PUBLIC_KEY"),
        "PRIVATE_KEY" => env("IMAGEKIT_PRIVATE_KEY"),
        "ENDPOINT" => env("IMAGEKIT_ENDPOINT"),
    ],
    "FILE_PATHS" => [
        "USER_PROFILE_PHOTO" => "/" . env("IMAGEKIT_ENV") . "/users/profile-photo/",
    ],
    "DEFAULT_FILES" => [
        "USER_PROFILE_PHOTO" => "default-avatar.png",
    ],
    "DEFAULTS" => [
        "COUNTRY_ID" => "1",
    ],
    "SEARCH_FILTERS" => [
        "LOCATION_SEARCH" => [
            "BY_DISTRICT" => "district",
            "BY_CITY" => "city",
        ],
    ],
];
