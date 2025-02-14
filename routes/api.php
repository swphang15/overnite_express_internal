<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportController;
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);


Route::apiResource('reports', ReportController::class);

