<?php

$router->post('/save-phone-for-sign-up', 'UserController@savePhoneNumber');
$router->post('/sign-up-user', 'UserController@signUp');
$router->get('/check-username-availability', 'UserController@checkUsernameAvailability');
$router->get('/check-email-availability', 'UserController@checkEmailAvailability');
