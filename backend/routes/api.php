<?php

use App\Http\Controllers\SoaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

Route::post('/soa/compute', [SoaController::class, 'compute']);
Route::get('/soa/history', [SoaController::class, 'history']);
Route::get('/soa/details', [SoaController::class, 'getSoaRunDetails']);
Route::get('/soa/cogs-lines', [SoaController::class, 'cogsLines']);
Route::get('/soa/ds-fee-lines', [SoaController::class, 'dsFeeLines']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
