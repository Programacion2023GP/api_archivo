<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\DepartamentController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\ProccessController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\SignatureProcedureController;
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




Route::middleware('auth:sanctum')->group(function () {
    Route::get('/logs', [LogController::class, 'index']);
    // Rutas protegidas por Sanctum
    Route::prefix('/departaments')->group(function () {
        Route::get('/index', [DepartamentController::class, 'index']);
    
        Route::post('/createorUpdate', [DepartamentController::class, 'createorUpdate']);
        Route::post('/authorized', [DepartamentController::class, 'authorized']);
        
        Route::delete('/delete', [DepartamentController::class, 'destroy']);
    });

    // Usuarios




    Route::prefix('/permissions')->group(function () {
        Route::get('/index', [PermissionController::class, 'index']);
    });
    Route::prefix('/proccess')->group(function () {
        Route::get('/index/{id}', [ProccessController::class, 'index']);
        Route::get('/processbyuser', [ProccessController::class, 'processByUser']);

        

        Route::post('/createorUpdate', [ProccessController::class, 'createorUpdate']);
        Route::delete('/delete', [ProccessController::class, 'destroy']);
    });
    Route::prefix('/signature')->group(function () {
        Route::post('/byuser', [SignatureProcedureController::class, 'signatureByUser']);
        Route::post('/listautorized', [SignatureProcedureController::class, 'listAutorized']);


    });
    Route::prefix('/procedure')->group(function () {
        Route::get('/index', [ProcedureController::class, 'index']);
        Route::get('/detailsprocedure/{created_at}/{departament_id}', [ProcedureController::class, 'detailsProcedure']);
        Route::get('/listautorized/{departament_id}', [ProcedureController::class, 'getAuthorizationChain']);

        // Route::get('/processbyuser', [ProccessController::class, 'processByUser']);
        // detailsProcedure
        Route::post('/changestatus', [ProcedureController::class, 'changeStatus']);

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
