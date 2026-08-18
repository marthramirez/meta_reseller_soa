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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
