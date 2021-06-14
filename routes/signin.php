<?php
$router->post('/auth/login', 'UserController@signIn');
$router->group(['middleware' => 'auth:api'], function ($router) {
    $router->post('sign-out-user', 'UserController@signOut');
    $router->post('refresh', 'UserController@refresh');
    $router->post('auth-user', 'UserController@authUser');
});
