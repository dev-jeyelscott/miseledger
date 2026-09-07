<?php

use App\Http\Controllers\Ai\AiAssistantController;
use App\Http\Controllers\Ai\AiConversationController;
use App\Http\Controllers\Ai\AiMessageController;
use App\Http\Controllers\Ai\AiRunController;
use App\Http\Controllers\Ai\CodexConnectionController;
use App\Mcp\Servers\MiseLedgerMcpServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('miseledger', MiseLedgerMcpServer::class);

Route::middleware(['auth', 'verified'])->prefix('ai')->name('ai.')->group(function (): void {
    Route::get('/', [AiAssistantController::class, 'index'])->name('index');
    Route::post('conversations', [AiConversationController::class, 'store'])->name('conversations.store');
    Route::delete('conversations/{conversation}', [AiConversationController::class, 'destroy'])->name('conversations.destroy');
    Route::post('conversations/{conversation}/messages', [AiMessageController::class, 'store'])->name('conversations.messages.store');
    Route::get('runs/{run}', [AiRunController::class, 'show'])->name('runs.show');
});

Route::middleware(['auth', 'verified'])->prefix('ai/codex')->name('ai.codex.')->group(function (): void {
    Route::get('connection', [CodexConnectionController::class, 'show'])->name('connection.show');
    Route::post('login', [CodexConnectionController::class, 'start'])->name('login.start');
    Route::post('login/complete', [CodexConnectionController::class, 'complete'])->name('login.complete');
    Route::delete('connection', [CodexConnectionController::class, 'destroy'])->name('connection.destroy');
    Route::get('rate-limits', [CodexConnectionController::class, 'rateLimits'])->name('rate-limits.show');
});
