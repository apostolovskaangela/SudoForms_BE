<?php

use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ProfessorController;
use App\Http\Controllers\Api\Admin\ResultController;
use App\Http\Controllers\Api\Admin\SurveyController as AdminSurveyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Student\ResponseController;
use App\Http\Controllers\Api\Student\SurveyController as StudentSurveyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
     | Admin (Student Success Team / Management)
     */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::get('/surveys', [AdminSurveyController::class, 'index']);
        Route::post('/surveys', [AdminSurveyController::class, 'store']);
        Route::get('/surveys/{survey}', [AdminSurveyController::class, 'show']);
        Route::put('/surveys/{survey}', [AdminSurveyController::class, 'update']);
        Route::delete('/surveys/{survey}', [AdminSurveyController::class, 'destroy']);
        Route::post('/surveys/{survey}/activate', [AdminSurveyController::class, 'activate']);
        Route::post('/surveys/{survey}/close', [AdminSurveyController::class, 'close']);
        Route::get('/surveys/{survey}/results', [ResultController::class, 'show']);

        Route::get('/courses', [CourseController::class, 'index']);
        Route::post('/courses', [CourseController::class, 'store']);
        Route::get('/professors', [ProfessorController::class, 'index']);
        Route::post('/professors', [ProfessorController::class, 'store']);
    });

    /*
     | Student
     */
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/surveys', [StudentSurveyController::class, 'index']);
        Route::get('/surveys/{survey}', [StudentSurveyController::class, 'show']);
        Route::post('/surveys/{survey}/submit', [ResponseController::class, 'store']);
    });
});
