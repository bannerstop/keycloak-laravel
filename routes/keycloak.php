<?php

use Bannerstop\KeycloakLaravel\Http\Controllers\BackchannelLogoutController;
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

// Called by Keycloak itself, server to server: no session, no CSRF token.
Route::post(config('keycloak.routes.prefix') . '/backchannel-logout', BackchannelLogoutController::class)->name('keycloak.backchannel-logout');
