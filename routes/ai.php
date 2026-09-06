<?php

use App\Http\Controllers\Ai\CodexConnectionController;
use App\Mcp\Servers\MiseLedgerMcpServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('miseledger', MiseLedgerMcpServer::class);

Route::middleware(['auth', 'verified'])->prefix('ai/codex')->name('ai.codex.')->group(function (): void {
    Route::get('connection', [CodexConnectionController::class, 'show'])->name('connection.show');
    Route::post('login', [CodexConnectionController::class, 'start'])->name('login.start');
    Route::post('login/complete', [CodexConnectionController::class, 'complete'])->name('login.complete');
    Route::delete('connection', [CodexConnectionController::class, 'destroy'])->name('connection.destroy');
    Route::get('rate-limits', [CodexConnectionController::class, 'rateLimits'])->name('rate-limits.show');
});
