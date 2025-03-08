<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeepSeekController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\AuthController;


Route::group([

    'middleware' => 'api',
    'prefix' => 'auth'

], function ($router) {

    //AUTH
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware('auth:api')->group(function () {

});
Route::post('/upload-exams', [ExamController::class, 'uploadAndCorrect']);
Route::get('/exams', [ExamController::class, 'getAllExams']);
Route::post('/review-exams', [ExamController::class, 'reviewExams']);

Route::post('/deepseek', [DeepSeekController::class, 'consultarDeepSeek']);
