<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\StartupController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::put('me', [AuthController::class, 'updateProfile']);
    Route::get('dashboard', DashboardController::class);
    Route::apiResource('startups', StartupController::class)->only(['store','show','update']);
    Route::apiResource('goals', GoalController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('activities', ActivityController::class);
    Route::get('ai/conversations', [AiController::class, 'conversations']);
    Route::post('ai/conversations', [AiController::class, 'createConversation']);
    Route::get('ai/conversations/{conversation}', [AiController::class, 'showConversation']);
    Route::get('ai/conversations/{conversation}/messages', [AiController::class, 'messages']);
    Route::post('ai/conversations/{conversation}/messages', [AiController::class, 'sendMessage'])->middleware('throttle:ai');
    Route::patch('ai/conversations/{conversation}', [AiController::class, 'renameConversation']);
    Route::get('ai/insights', [AiController::class, 'insights']);
    Route::post('ai/analyze', [AiController::class, 'analyze'])->middleware('throttle:ai');
    Route::get('ai/analysis', [AiController::class, 'analysis'])->middleware('throttle:ai');
    Route::get('ai/goal-health', [AiController::class, 'goalHealth']);
    Route::get('ai/next-actions', [AiController::class, 'nextActions']);
    Route::post('ai/recommendation/to-task', [AiController::class, 'recommendationToTask']);
    Route::post('ai/actions/next-move', [AiController::class, 'nextMove'])->middleware('throttle:ai');
    Route::post('ai/goals/{goal}/analyze', [AiController::class, 'analyzeGoal'])->middleware('throttle:ai');
    Route::post('ai/tasks/{task}/analyze', [AiController::class, 'analyzeTask'])->middleware('throttle:ai');
    Route::post('ai/activities/{activity}/analyze', [AiController::class, 'analyzeActivity'])->middleware('throttle:ai');
});
