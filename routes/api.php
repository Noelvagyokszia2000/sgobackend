<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklySubmissionController;
use App\Http\Controllers\WarnController;
use App\Http\Controllers\ParkingController;
use App\Http\Controllers\RankController;
use App\Http\Controllers\RankupController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\RefundRequestController;
use App\Http\Controllers\RobberyController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\DiscordBotController;
use App\Http\Controllers\InactivityRequestController;

Route::get('/user/{id}', [UserController::class, 'index']);
Route::post('/add-user', [UserController::class, 'addUser']);
Route::delete('/user/{id}', [UserController::class, 'destroy']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/change-name', [UserController::class, 'changeName']);
Route::get('/is-admin/{id}', [UserController::class, 'isAdmin']);
Route::get('/users', [UserController::class, 'getAllUsers']);
Route::post('/change-username', [UserController::class, 'changeUsername']);
Route::post('/change-password', [UserController::class, 'changePassword']);
Route::post('/upload-profile-image', [UserController::class, 'uploadProfileImage']);
Route::post('/change-rank', [UserController::class, 'changeRank']);
Route::patch('/users/{id}/weekly-payment-required', [UserController::class, 'updateWeeklyPaymentRequired']);
Route::patch('/users/{id}/successful-cassettes', [UserController::class, 'incrementSuccessfulCassettes']);
Route::get('/cassette-leaderboard', [UserController::class, 'cassetteLeaderboard']);


Route::get('/ranks', [RankController::class, 'index']);
Route::get('/ranks/available', [RankController::class, 'available']);


Route::get('/weekly-submission/pending', [WeeklySubmissionController::class, 'pending']);
Route::patch('/weekly-submission/{id}/accept', [WeeklySubmissionController::class, 'accept']);
Route::post('/weekly-submission', [WeeklySubmissionController::class, 'store']);
Route::get('/weekly-submission/{userId}', [WeeklySubmissionController::class, 'index']);


Route::get('/inactivity-requests/admin', [InactivityRequestController::class, 'adminIndex']);
Route::get('/inactivity-requests/user/{userId}', [InactivityRequestController::class, 'byUser']);
Route::post('/inactivity-requests', [InactivityRequestController::class, 'store']);
Route::patch('/inactivity-requests/{id}/approve', [InactivityRequestController::class, 'approve']);
Route::patch('/inactivity-requests/{id}/reject', [InactivityRequestController::class, 'reject']);


Route::get('/refund-requests', [RefundRequestController::class, 'index']);
Route::get('/refund-requests/pending', [RefundRequestController::class, 'pending']);
Route::get('/refund-requests/user/{userId}', [RefundRequestController::class, 'byUser']);
Route::post('/refund-requests', [RefundRequestController::class, 'store']);
Route::patch('/refund-requests/{id}/approve', [RefundRequestController::class, 'approve']);
Route::patch('/refund-requests/{id}/reject', [RefundRequestController::class, 'reject']);


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


Route::get('/vehicles', [VehicleController::class, 'index']);
Route::post('/vehicles', [VehicleController::class, 'store']);
Route::get('/vehicles/key-requests/pending', [VehicleController::class, 'pendingKeyRequests']);
Route::patch('/vehicles/{id}/request-key', [VehicleController::class, 'requestKey']);
Route::patch('/vehicles/key-requests/{id}/accept', [VehicleController::class, 'acceptKeyRequest']);
Route::patch('/vehicles/key-requests/{id}/reject', [VehicleController::class, 'rejectKeyRequest']);
Route::delete('/vehicles/{vehicleId}/keys/{userId}', [VehicleController::class, 'removeKey']);
Route::post('/vehicles/{id}/warnings', [VehicleController::class, 'addWarning']);
Route::delete('/vehicles/warnings/{id}', [VehicleController::class, 'deleteWarning']);
Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);


Route::get('/expenses', [ExpenseController::class, 'index']);
Route::post('/expenses', [ExpenseController::class, 'store']);


Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/admin', [NewsController::class, 'adminIndex']);
Route::post('/news', [NewsController::class, 'store']);
Route::delete('/news/{id}', [NewsController::class, 'destroy']);


Route::get('/robberies', [RobberyController::class, 'index']);
Route::post('/robberies', [RobberyController::class, 'store']);
Route::post('/robberies/{id}/join', [RobberyController::class, 'join']);
Route::delete('/robberies/{id}/join', [RobberyController::class, 'leave']);
Route::post('/robberies/{id}/payout-request', [RobberyController::class, 'requestPayout']);
Route::delete('/robberies/{id}/payout-request', [RobberyController::class, 'cancelPayout']);
Route::post('/robberies/{id}/income-images', [RobberyController::class, 'storeIncome']);
Route::patch('/robberies/{id}/finished', [RobberyController::class, 'updateFinished']);
Route::delete('/robberies/{id}', [RobberyController::class, 'destroy']);


Route::get('/bot/robberies/pending-announcements', [DiscordBotController::class, 'pendingRobberies']);
Route::get('/bot/poll', [DiscordBotController::class, 'poll']);
Route::post('/bot/robberies', [DiscordBotController::class, 'createRobbery']);
Route::patch('/bot/robberies/{id}/discord-message', [DiscordBotController::class, 'recordDiscordMessage']);
Route::post('/bot/robberies/discord-reaction', [DiscordBotController::class, 'handleReaction']);
Route::post('/bot/robberies/discord-reaction/remove', [DiscordBotController::class, 'removeReaction']);
Route::get('/bot/discord-reaction-removals', [DiscordBotController::class, 'pendingDiscordReactionRemovals']);
Route::patch('/bot/discord-reaction-removals/{id}/processed', [DiscordBotController::class, 'markDiscordReactionRemovalProcessed']);
Route::get('/bot/news/pending-announcements', [DiscordBotController::class, 'pendingNews']);
Route::patch('/bot/news/{id}/discord-message', [DiscordBotController::class, 'recordNewsDiscordMessage']);
Route::get('/bot/weekly-payment-reminders', [DiscordBotController::class, 'pendingWeeklyPaymentReminders']);
Route::patch('/bot/weekly-payment-reminders/{id}/sent', [DiscordBotController::class, 'markWeeklyPaymentReminderSent']);
Route::get('/bot/inactivity-reminders', [DiscordBotController::class, 'pendingInactivityReminders']);
Route::patch('/bot/inactivity-reminders/{id}/sent', [DiscordBotController::class, 'markInactivityReminderSent']);
Route::get('/bot/users/linked/{discordId}', [DiscordBotController::class, 'linkedUser']);
Route::post('/bot/users/unlink', [DiscordBotController::class, 'unlinkUser']);
Route::post('/bot/users/link', [DiscordBotController::class, 'linkUser']);
