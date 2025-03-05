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



Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/users', [UserController::class, 'createUser']); // 创建用户
    Route::get('/users/{id?}', [UserController::class, 'readUser']); // 读取所有用户或单个用户
    Route::put('/users/{id}', [UserController::class, 'updateUser']); // 更新用户
    Route::delete('/users/{id}', [UserController::class, 'deleteUser']); // 删除用户
});
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/shipping-rates', [ShippingController::class, 'index']); 
    Route::post('/shipping-rates', [ShippingController::class, 'store']);
    Route::put('/shipping-rates/{id}', [ShippingController::class, 'update']);
    Route::delete('/shipping-rates/{id}', [ShippingController::class, 'destroy']);
    Route::post('/calculate-shipping', [ShippingController::class, 'calculateShipping']);
});
Route::get('/shipping-rates/origins', [ShippingController::class, 'getUniqueOrigins']); // 获取唯一origin
Route::get('/shipping-rates/destinations', [ShippingController::class, 'getUniqueDestinations']); // 获取唯一destination


Route::post('/export-excel', [InvoiceExportController::class, 'exportExcel']);

Route::post('/manifest/pdf', [InvoiceExportController::class, 'exportPDF']);
Route::post('/manifest/pdf', [InvoiceExportController::class, 'exportPDF']);
Route::get('/manifest/pdf/{id}', [InvoiceExportController::class, 'exportPDF']);



Route::get('/clients', [ClientController::class, 'index']);
Route::get('/clients/{id}', [ClientController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/client/profile', [ClientController::class, 'profile']);
    Route::put('/client/update', [ClientController::class, 'updateProfile']);
    Route::post('/client/change_password', [ClientController::class, 'changePassword']);
});




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
