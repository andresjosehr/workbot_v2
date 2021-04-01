<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

$router->get('/', function(){
    return "Workbot 1.0";
});

Route::post('config', "App\Http\Controllers\TasksController@setConfig");

Route::get('trigger-alerts', "App\Http\Controllers\TasksController@triggerAlerts");
