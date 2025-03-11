<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\InvoiceExportController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ShippingPlanController;
use App\Http\Controllers\ShippingRateController;

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/users', [UserController::class, 'createUser']); 
    Route::get('/users/{id?}', [UserController::class, 'readUser']); 
    Route::put('/users/{id}', [UserController::class, 'updateUser']); 
    Route::delete('/users/{id}', [UserController::class, 'deleteUser']); 
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/clients', [ClientController::class, 'index']); 
    Route::post('/clients', [ClientController::class, 'store']); 
    Route::get('/clients/{id}', [ClientController::class, 'show']); 
    Route::put('/clients/{id}', [ClientController::class, 'update']); 
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']); 
    Route::get('/clients/trashed', [ClientController::class, 'trashed']); 
    Route::post('/clients/restore/{id}', [ClientController::class, 'restore']); 
});

Route::post('/shipping-plan-rates', [ShippingController::class, 'store']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/shipping-plans', [ShippingPlanController::class, 'index']); 
    Route::post('/shipping-plans', [ShippingPlanController::class, 'store']); 
    Route::get('/shipping-plans/{id}', [ShippingPlanController::class, 'show']); 
    Route::put('/shipping-plans/{id}', [ShippingPlanController::class, 'update']); 
    Route::delete('/shipping-plans/{id}', [ShippingPlanController::class, 'destroy']); 
    Route::get('/shipping-plans/trashed', [ShippingPlanController::class, 'trashed']); 
    Route::post('/shipping-plans/restore/{id}', [ShippingPlanController::class, 'restore']); 
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/shipping-rates', [ShippingRateController::class, 'index']); 
    Route::post('/shipping-rates', [ShippingRateController::class, 'store']); 
    Route::get('/shipping-rates/unique', [ShippingRateController::class, 'OAD']);
    Route::get('/shipping-rates/{id}', [ShippingRateController::class, 'show']); 
    Route::put('/shipping-rates/{id}', [ShippingRateController::class, 'update']); 
    Route::delete('/shipping-rates/{id}', [ShippingRateController::class, 'destroy']); 
    Route::get('/shipping-rates/trashed', [ShippingRateController::class, 'trashed']); 
    Route::post('/shipping-rates/restore/{id}', [ShippingRateController::class, 'restore']);
});




Route::get('/shipping-rates/origins', [ShippingController::class, 'getUniqueOrigins']); // 获取唯一origin
Route::get('/shipping-rates/destinations', [ShippingController::class, 'getUniqueDestinations']); // 获取唯一destination






Route::post('/export-excel', [InvoiceExportController::class, 'exportExcel']);

Route::post('/manifest/pdf', [InvoiceExportController::class, 'exportPDF']);
Route::post('/manifest/pdf', [InvoiceExportController::class, 'exportPDF']);
Route::get('/manifest/pdf/{id}', [InvoiceExportController::class, 'exportPDF']);


Route::get('/create_manifest_form_data', [ManifestController::class, 'createManifestFormData']);

Route::apiResource('manifests', ManifestController::class);

Route::post('/manifests', [ManifestController::class, 'store']);
Route::put('/manifests/manifest', [ManifestController::class, 'update']);
// 删除 manifest
Route::delete('/manifests/{id}', [ManifestController::class, 'destroy']);


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
