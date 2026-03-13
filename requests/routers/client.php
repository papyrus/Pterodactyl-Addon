<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ProtectionController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\DomainController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\AntibotController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\MessagesController;

// Blueprint auto-prefixes: /api/client/papyrusprotection/

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/protection/{server}', [ProtectionController::class, 'index']);
    Route::get('/domains/{server}', [DomainController::class, 'index']);
    Route::get('/antibot/{server}', [AntibotController::class, 'index']);
    Route::get('/messages/{server}', [MessagesController::class, 'index']);
});

Route::middleware('throttle:15,1')->group(function () {
    Route::post('/domains/{server}', [DomainController::class, 'store']);
    Route::post('/domains/{server}/{domain}/verify', [DomainController::class, 'verify']);
    Route::delete('/domains/{server}/{domain}', [DomainController::class, 'destroy']);

    Route::put('/antibot/{server}', [AntibotController::class, 'update']);
    Route::post('/antibot/{server}/reset', [AntibotController::class, 'reset']);
    Route::put('/antibot/{server}/proxy-protocol', [AntibotController::class, 'toggleProxyProtocol']);

    Route::put('/messages/{server}', [MessagesController::class, 'update']);
    Route::post('/messages/{server}/reset', [MessagesController::class, 'reset']);
});
