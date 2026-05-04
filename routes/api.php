<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklySubmissionController;
use App\Http\Controllers\WarnController;
use App\Http\Controllers\ParkingController;
use App\Http\Controllers\RankController;
use App\Http\Controllers\RankupController;

Route::get('/user/{id}', [UserController::class, 'index']);
Route::post('/add-user', [UserController::class, 'addUser']);
Route::delete('/user/{id}', [UserController::class, 'destroy']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/change-name', [UserController::class, 'changeName']);
Route::get('/is-admin/{id}', [UserController::class, 'isAdmin']);
Route::get('/users', [UserController::class, 'getAllUsers']);
Route::post('/change-username', [UserController::class, 'changeUsername']);
Route::post('/change-password', [UserController::class, 'changePassword']);
Route::post('/change-rank', [UserController::class, 'changeRank']);


Route::get('/ranks', [RankController::class, 'index']);
Route::get('/ranks/available', [RankController::class, 'available']);


Route::get('/weekly-submission/pending', [WeeklySubmissionController::class, 'pending']);
Route::patch('/weekly-submission/{id}/accept', [WeeklySubmissionController::class, 'accept']);
Route::post('/weekly-submission', [WeeklySubmissionController::class, 'store']);
Route::get('/weekly-submission/{userId}', [WeeklySubmissionController::class, 'index']);


Route::post('/warns', [WarnController::class, 'addWarn']);
Route::delete('/warns/{id}', [WarnController::class, 'removeWarn']);
Route::get('/warns/user/{userId}', [WarnController::class, 'getUserWarns']);
Route::get('/warns', [WarnController::class, 'getAllWarns']);


Route::get('/parkings', [ParkingController::class, 'index']);
Route::patch('/parkings/{id}/claim', [ParkingController::class, 'claim']);
Route::patch('/parkings/{id}/release', [ParkingController::class, 'release']);


Route::get('/rankups/pending', [RankupController::class, 'pending']);
Route::get('/rankups', [RankupController::class, 'index']);
Route::post('/rankups', [RankupController::class, 'store']);
Route::patch('/rankups/{id}/complete', [RankupController::class, 'markCompleted']);