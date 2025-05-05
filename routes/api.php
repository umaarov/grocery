<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/email/verify/{id}/{token}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('api.verification.verify');

Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect'])->name('api.auth.google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->name('api.auth.google.callback');


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware(['throttle:6,1']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
