<?php

use App\Http\Controllers\Admin\BitacoraController;
use App\Http\Controllers\Admin\TiendaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CronogramaController;
use App\Http\Controllers\ListadoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProductoController;
use App\Http\Middleware\RolMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/inicio'));

// Acceso
Route::get('/ingresar', [AuthController::class, 'formulario']);
Route::post('/ingresar', [AuthController::class, 'ingresar']);
Route::post('/salir', [AuthController::class, 'salir']);

// Cualquier usuario con sesión: lo manda a su pantalla según el rol
Route::get('/inicio', [AuthController::class, 'inicio'])->middleware(RolMiddleware::class);

// Administrador del sistema
Route::middleware(RolMiddleware::class . ':admin_sistema')->prefix('admin')->group(function () {
    Route::get('/tiendas', [TiendaController::class, 'index']);
    Route::post('/tiendas', [TiendaController::class, 'store']);
    Route::post('/tiendas/{id}/estado', [TiendaController::class, 'cambiarEstado']);
    Route::get('/bitacora', [BitacoraController::class, 'index']);
});

// Administrador de la tienda
Route::middleware(RolMiddleware::class . ':admin_tienda')->group(function () {
    Route::get('/panel', [PanelController::class, 'tienda']);

    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::get('/clientes/nuevo', [ClienteController::class, 'formulario']);
    Route::post('/clientes', [ClienteController::class, 'guardar']);
    Route::post('/clientes/{id}/estado', [ClienteController::class, 'cambiarEstado']);

    Route::get('/productos', [ProductoController::class, 'index']);
    Route::get('/productos/nuevo', [ProductoController::class, 'formulario']);
    Route::post('/productos', [ProductoController::class, 'guardar']);
    Route::post('/productos/{id}/estado', [ProductoController::class, 'cambiarEstado']);
    Route::post('/productos/{id}/imagen', [ProductoController::class, 'cambiarImagen']);

    Route::get('/compras', [CompraController::class, 'index']);
    Route::get('/compras/nueva', [CompraController::class, 'formulario']);
    Route::post('/compras', [CompraController::class, 'guardar']);
    Route::get('/compras/{id}', [CompraController::class, 'ver']);

    Route::get('/pagos', [PagoController::class, 'index']);
    Route::get('/pagos/{obligacion}/nuevo', [PagoController::class, 'formulario']);
    Route::post('/pagos/{obligacion}', [PagoController::class, 'guardar']);

    Route::get('/listados', [ListadoController::class, 'index']);
    Route::get('/listados/{cliente}', [ListadoController::class, 'ver']);
    Route::post('/listados/{cliente}/guardar', [ListadoController::class, 'guardar']);
});

// Cliente (solo consulta lo suyo)
Route::middleware(RolMiddleware::class . ':cliente')->group(function () {
    Route::get('/mi-cuenta', [PanelController::class, 'cliente']);
    Route::get('/mi-cuenta/compras/{id}', [PanelController::class, 'clienteCompra']);
    Route::get('/mi-cuenta/listado', [PanelController::class, 'clienteListado']);
    Route::get('/mi-cuenta/catalogo', [PanelController::class, 'clienteCatalogo']);
});

// Simulador de cronograma (tienda y cliente)
Route::middleware(RolMiddleware::class . ':admin_tienda,cliente')->group(function () {
    Route::get('/cronograma', [CronogramaController::class, 'formulario']);
    Route::post('/cronograma', [CronogramaController::class, 'calcular']);
});

require __DIR__ . '/settings.php';
