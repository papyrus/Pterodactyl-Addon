<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ApiController;

// Blueprint auto-prefixes: /api/application/papyrusprotection/

Route::get('/overview', [ApiController::class, 'overview']);
Route::get('/servers/{server}/status', [ApiController::class, 'status']);
Route::post('/servers/{server}/protect', [ApiController::class, 'protect']);
Route::delete('/servers/{server}/protect', [ApiController::class, 'unprotect']);
