<?php

use App\Http\Controllers\ApiController;
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

Route::controller(ApiController::class)->group(function () {
    Route::post("/register", "register");
    Route::post("/login", "login");
    Route::get("/airport", "airport");
    Route::get("/flight", "flight");
    Route::post("/booking", "booking");
    Route::get("/booking/{code}", "booking_show");
    Route::match(["get", 'patch'], "/booking/{code}/seat", "seat");
    Route::get("/user", "user");
    Route::get("/user/booking", "user_booking");
});
