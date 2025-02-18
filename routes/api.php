<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\CompanyController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::post('/calculate-shipping', [ShippingController::class, 'calculateShipping']);


Route::get('/companies', [CompanyController::class, 'index']);
Route::apiResource('manifests', ManifestController::class);
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
