<?php

use Bannerstop\KeycloakLaravel\Http\Controllers\KeycloakController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('keycloak.routes.prefix'),
    'middleware' => config('keycloak.routes.middleware'),
], function () {
    Route::get('login', KeycloakController::class . '@login')->name('keycloak.login');
    Route::get('callback', KeycloakController::class . '@callback')->name('keycloak.callback');
    Route::post('logout', KeycloakController::class . '@logout')->name('keycloak.logout');
});
