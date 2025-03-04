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

Route::post('/export-excel', [InvoiceExportController::class, 'exportExcel']);

Route::post('/manifest/pdf', [InvoiceExportController::class, 'exportPDF']);
Route::post('/manifest/pdf', [InvoiceExportController::class, 'exportPDF']);
Route::get('/manifest/pdf/{id}', [InvoiceExportController::class, 'exportPDF']);


Route::post('/register', [ClientController::class, 'register']);
Route::post('/login', [ClientController::class, 'login']);
Route::get('/clients', [ClientController::class, 'index']);
Route::get('/clients/{id}', [ClientController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/client/profile', [ClientController::class, 'profile']);
    Route::put('/client/update', [ClientController::class, 'updateProfile']);
    Route::post('/client/change_password', [ClientController::class, 'changePassword']);
});


Route::get('/shipping-rates/origins', [ShippingController::class, 'getUniqueOrigins']);
Route::get('/shipping-rates/destinations', [ShippingController::class, 'getUniqueDestinations']);

Route::get('/shipping_rates', [ShippingController::class, 'index']);
Route::post('/shipping_rates', [ShippingController::class, 'store']);
Route::post('/calculate_shipping', [ShippingController::class, 'calculateShipping']);

Route::get('/create_manifest_form_data', [ManifestController::class, 'createManifestFormData']);

Route::get('/agents', function () {
    return response()->json(Agent::all());
});
Route::get('/clients/{id}', [ClientController::class, 'show']);

Route::get('/clients', [ClientController::class, 'index']);

Route::apiResource('manifests', ManifestController::class);

Route::post('/manifests', [ManifestController::class, 'store']);
Route::put('/manifests/manifest', [ManifestController::class, 'update']);
// 删除 manifest
Route::delete('/manifests/{id}', [ManifestController::class, 'destroy']);


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
