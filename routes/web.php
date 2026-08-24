<?php

use App\Http\Controllers\AchievementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get(
    'users/{user}/achievements',
    [AchievementController::class, 'index']
);