<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\DepartamentController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\ProccessController;
use App\Http\Controllers\ProcedureController;
use App\Models\Departament;

// Rutas públicas

Route::get('/hola', function () {
    return response()->json(['message' => '¡Hola!']);
});
Route::prefix('/users')->group(
    function () {

        Route::post('/login', [UserController::class, 'login']);
    }
);




// Rutas protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/logs', [LogController::class, 'index']);

    // Usuarios


    Route::prefix('/departaments')->group(function () {
        Route::get('/index', [DepartamentController::class, 'index']);
        Route::post('/createorUpdate', [DepartamentController::class, 'createorUpdate']);
        Route::post('/authorized', [DepartamentController::class, 'authorized']);

        Route::delete('/delete', [DepartamentController::class, 'destroy']);
    });


    Route::prefix('/permissions')->group(function () {
        Route::get('/index', [PermissionController::class, 'index']);
    });
    Route::prefix('/proccess')->group(function () {
        Route::get('/index/{id}', [ProccessController::class, 'index']);
        Route::get('/processbyuser', [ProccessController::class, 'processByUser']);

        

        Route::post('/createorUpdate', [ProccessController::class, 'createorUpdate']);
        Route::delete('/delete', [ProccessController::class, 'destroy']);
    });

    Route::prefix('/procedure')->group(function () {
        Route::get('/index', [ProcedureController::class, 'index']);
        // Route::get('/processbyuser', [ProccessController::class, 'processByUser']);



        Route::post('/createorUpdate', [ProcedureController::class, 'createorUpdate']);
        // Route::delete('/delete', [ProccessController::class, 'destroy']);
    });

    Route::prefix('/users')->group(function () {

        Route::post('/createorUpdate', [UserController::class, 'register']);

        Route::get('/index', [UserController::class, 'index']);
        Route::delete('/delete', [UserController::class, 'destroy']);
    });


    // Dependencias

    // Procedimientos


    // Permisos

    // Técnicos



});
