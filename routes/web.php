<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get(
    'users/{user}/achievements',
    [AchievementController::class, 'index']
);

Route::post(
    'users/{user}/purchases',
    [PurchaseController::class, 'store']
);
