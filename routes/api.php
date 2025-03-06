<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeepSeekController;
use App\Http\Controllers\ExamController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/upload-exams', [ExamController::class, 'uploadAndCorrect']);
Route::get('/exams', [ExamController::class, 'getAllExams']);
Route::post('/review-exams', [ExamController::class, 'reviewExams']);
Route::post('/deepseek', [DeepSeekController::class, 'consultarDeepSeek']);
