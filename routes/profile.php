<?php
$router->group([
    'middleware' => 'auth:api',
    'prefix' => '/profile'
], function ($router) {
    $router->put('/change-password', 'UserController@changeUserProfilePassword');
    $router->put('/update', 'UserController@updateProfile');
    $router->get('/check-username-availability', 'UserController@isUsernameAvailableForExistingProfileDirect');
    $router->get('/check-email-availability', 'UserController@isEmailAvailableForExistingProfileDirect');
    $router->post('/update-avatar', 'UserController@storeProfileAvatar');
    $router->put('/update-mobile-number', 'UserController@updateMobileNumber');
    $router->post('/mobile-number-code-verification', 'UserController@updateMobileNumberCodeVerification');
    $router->post('/send-email', 'UserController@sendMail');
});
$router->group(['prefix' => '/profile'], function ($router) {
});
