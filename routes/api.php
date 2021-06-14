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

$router->group(['prefix' => '/category'], function () use ($router) {
    $router->get('/suggestions', 'CategoryController@getCategorySuggestions');
    $router->get('/suggestions-of-sub-1-category', 'CategoryController@getSub1CategorySuggestions');
    $router->get('/suggestions-of-sub-2-category', 'CategoryController@getSub2CategorySuggestions');
    $router->get('/suggestions-of-sub-3-category', 'CategoryController@getSub3CategorySuggestions');
    $router->post('/add', 'CategoryController@addCategory');
    $router->get('/view-all-categories', 'CategoryController@viewAllCategories');
    $router->get('/get-selected-category-details-for-view', 'CategoryController@getSelectedCategoryDetails');
    $router->post('/add-and-copy', 'CategoryController@addCategoryAndCodyAdditionalFields');
    $router->group(['prefix' => '/edit'], function () use ($router) {
        $router->get('/get-category-tree-details-by-id', 'CategoryController@getCategoryDetailsById');
        $router->get('/get-selected-category-details', 'CategoryController@getSelectedCategoryDetailsForEdit');
    });
    $router->put('/update', 'CategoryController@updateCategory');
    $router->delete('/delete', 'CategoryController@deleteCategory');

});

$router->group(['prefix' => '/location'], function () use ($router) {
    $router->post('/add', 'LocationController@addLocation');
    $router->get('/view-all-locations', 'LocationController@viewAllLocations');
    $router->group(['prefix' => '/edit'], function () use ($router) {
        $router->get('/get-location-tree-by-id', 'LocationController@getLocationById');
    });
    $router->put('/update', 'LocationController@updateLocation');
    $router->delete('/delete', 'LocationController@deleteLocation');
});

$router->group(['prefix' => '/sell'], function () use ($router) {
    $router->get('/get-lookup-categories', 'SellController@getLookupCategories');
    $router->get('/get-category-id-by-type', 'SellController@getCategoryIdByType');
    $router->post('/add-product', 'SellController@addProduct');
});

$router->get('/get-imagekit-token', 'SellController@getImagekitToken');
