<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Usuarios
    Route::apiResource('/users', UserController::class);

    // Contactos
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts/import', [ContactController::class, 'import']);
    Route::patch('/contacts/{id}', [ContactController::class, 'update']);
    Route::post('/contacts', [ContactController::class, 'store']);

    //  Clientes
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
});
