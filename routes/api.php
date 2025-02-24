<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ShippingController;
use App\Models\Agent;
use App\Models\Company;
use App\Http\Controllers\InvoiceExportController;


Route::get('/manifest/pdf/{id}', [InvoiceExportController::class, 'exportPDF']);
Route::get('/manifest/excel/{id}', [InvoiceExportController::class, 'exportExcel']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::get('/shipping-rates', [ShippingController::class, 'index']);
Route::post('/shipping-rates', [ShippingController::class, 'store']);
Route::post('/calculate-shipping', [ShippingController::class, 'calculateShipping']);


Route::get('/agents', function () {
    return response()->json(Agent::all());
});

Route::get('/companies', function () {
    return response()->json(Company::all());
});
Route::apiResource('manifests', ManifestController::class);
Route::post('/manifests', [ManifestController::class, 'store']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
