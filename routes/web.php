<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

if (!app()->environment('prod')) {
    $router->get('/key', function () {
        return \Illuminate\Support\Str::random(32);
    });
}

$router->get('/get-all-countries', 'LookUpController@getAllCountries');
$router->get('/get-all-districts', 'LookUpController@getAllDistricts');
$router->get('/get-cities', 'LookUpController@getCities');
$router->get('/get-genders', 'LookUpController@getGenders');
