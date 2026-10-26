<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CasaController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\HabitacionController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => $request->user());

    Route::get('/empresas', [EmpresaController::class, 'index']);
    Route::middleware('permission:gestionar_empresas')->group(function () {
        Route::post('/empresas', [EmpresaController::class, 'store']);
        Route::put('/empresas/{id}', [EmpresaController::class, 'update']);
        Route::patch('/empresas/{id}/activo', [EmpresaController::class, 'toggleActivo']);
        Route::post('/empresas/{id}/usuarios', [EmpresaController::class, 'syncUsuarios']);
    });

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:ver_roles');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:crear_roles');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->middleware('permission:editar_roles');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:eliminar_roles');
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:ver_permisos');

    Route::get('/users', [UserRoleController::class, 'index'])->middleware('permission:ver_usuarios');
    Route::post('/users', [UserRoleController::class, 'store'])->middleware('permission:crear_usuarios');
    Route::middleware('permission:editar_usuarios')->group(function () {
        Route::put('/users/{id}', [UserRoleController::class, 'update']);
        Route::post('/users/{id}/assign-roles', [UserRoleController::class, 'assignRoles']);
    });
    Route::delete('/users/{id}', [UserRoleController::class, 'destroy'])->middleware('permission:eliminar_usuarios');

    Route::middleware('empresa')->group(function () {
        Route::get('/casas', [CasaController::class, 'index']);
        Route::middleware('permission:gestionar_casas')->group(function () {
            Route::post('/casas', [CasaController::class, 'store']);
            Route::put('/casas/{id}', [CasaController::class, 'update']);
            Route::patch('/casas/{id}/activo', [CasaController::class, 'toggleActivo']);
        });

        Route::middleware('permission:ver_habitaciones|gestionar_habitaciones')->group(function () {
            Route::get('/habitaciones', [HabitacionController::class, 'index']);
        });
        Route::middleware('permission:gestionar_habitaciones')->group(function () {
            Route::post('/habitaciones', [HabitacionController::class, 'store']);
            Route::put('/habitaciones/{id}', [HabitacionController::class, 'update']);
            Route::patch('/habitaciones/{id}/activo', [HabitacionController::class, 'toggleActivo']);
        });

        Route::middleware('permission:ver_reservas|gestionar_reservas')->group(function () {
            Route::get('/reservas', [ReservaController::class, 'index']);
            Route::get('/reservas/disponibilidad', [ReservaController::class, 'disponibilidad']);
            Route::get('/reservas/calendario', [ReservaController::class, 'calendario']);
            Route::get('/reservas/calendario/pdf', [ReservaController::class, 'calendarioPdf']);
        });
        Route::middleware('permission:gestionar_reservas')->group(function () {
            Route::post('/reservas', [ReservaController::class, 'store']);
            Route::put('/reservas/{id}', [ReservaController::class, 'update']);
            Route::patch('/reservas/{id}/cancelar', [ReservaController::class, 'cancelar']);
            Route::patch('/reservas/{id}/confirmar', [ReservaController::class, 'confirmar']);
        });
    });
});
