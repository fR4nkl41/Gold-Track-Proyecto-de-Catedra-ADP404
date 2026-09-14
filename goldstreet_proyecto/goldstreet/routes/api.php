<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CuentaPagarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PlatoController;
use App\Http\Controllers\ProduccionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Pública (máx. 5 intentos por minuto por IP: frena fuerza bruta)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Protegidas con token Sanctum (Bearer)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',       [AuthController::class, 'me']);
    Route::post('/logout',  [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Catálogos · lectura: cualquier rol
    Route::get('/categorias',  [CatalogoController::class, 'categorias']);
    Route::get('/proveedores', [CatalogoController::class, 'proveedores']);

    // Lectura: cualquier rol autenticado · Escritura: solo admin (EnsureRole)
    Route::get('/productos',           [ProductoController::class, 'index']);
    Route::get('/productos/{producto}', [ProductoController::class, 'show']);

    // La matriz rol+tipo se aplica DENTRO del controller (sección 4.4)
    Route::get('/movimientos',  [MovimientoInventarioController::class, 'index']);
    Route::post('/movimientos', [MovimientoInventarioController::class, 'store']);

    // ---- Fase 2 · Facturas de compra: bodega y admin (los precios solo los ve el admin)
    Route::middleware('role:admin,bodega')->group(function () {
        Route::get('/facturas',           [FacturaController::class, 'index']);
        Route::get('/facturas/{factura}', [FacturaController::class, 'show']);
        Route::post('/facturas',          [FacturaController::class, 'store']);
    });

    // ---- Fase 2 · Producción / porcionado: todos consultan, admin y cocina registran
    Route::get('/producciones',  [ProduccionController::class, 'index']);
    Route::post('/producciones', [ProduccionController::class, 'store'])->middleware('role:admin,cocina');

    // ---- Fase 3 · Recetas: todos consultan (costos solo admin)
    Route::get('/platos', [PlatoController::class, 'index']);

    // ---- Fase 3 · Lista de compras y órdenes de compra: admin y bodega (precios solo admin)
    Route::middleware('role:admin,bodega')->group(function () {
        Route::get('/compras/sugerencia',              [OrdenCompraController::class, 'sugerencia']);
        Route::get('/ordenes-compra',                  [OrdenCompraController::class, 'index']);
        Route::get('/ordenes-compra/{orden}',          [OrdenCompraController::class, 'show']);
        Route::post('/ordenes-compra',                 [OrdenCompraController::class, 'store']);
        Route::post('/ordenes-compra/{orden}/cancelar', [OrdenCompraController::class, 'cancelar']);
    });

    // ---- Solo administrador -------------------------------------------------
    Route::middleware('role:admin')->group(function () {
        Route::post('/productos',              [ProductoController::class, 'store']);
        Route::put('/productos/{producto}',    [ProductoController::class, 'update']);
        Route::delete('/productos/{producto}', [ProductoController::class, 'destroy']);

        Route::post('/categorias',               [CatalogoController::class, 'storeCategoria']);
        Route::put('/categorias/{categoria}',    [CatalogoController::class, 'updateCategoria']);
        Route::delete('/categorias/{categoria}', [CatalogoController::class, 'destroyCategoria']);

        Route::post('/proveedores',               [CatalogoController::class, 'storeProveedor']);
        Route::put('/proveedores/{proveedor}',    [CatalogoController::class, 'updateProveedor']);
        Route::delete('/proveedores/{proveedor}', [CatalogoController::class, 'destroyProveedor']);
        Route::get('/proveedores/{proveedor}/ficha', [CatalogoController::class, 'fichaProveedor']);

        Route::post('/facturas/{factura}/anular', [FacturaController::class, 'anular']);

        // Fase 3 · Menú y recetas
        Route::post('/platos',           [PlatoController::class, 'store']);
        Route::put('/platos/{plato}',    [PlatoController::class, 'update']);
        Route::delete('/platos/{plato}', [PlatoController::class, 'destroy']);

        // Fase 3 · Cuentas por pagar
        Route::get('/cuentas-por-pagar',          [CuentaPagarController::class, 'index']);
        Route::post('/facturas/{factura}/pagos',  [CuentaPagarController::class, 'pagar']);

        Route::get('/usuarios',            [UsuarioController::class, 'index']);
        Route::post('/usuarios',           [UsuarioController::class, 'store']);
        Route::put('/usuarios/{usuario}',  [UsuarioController::class, 'update']);

        // Sprint 2 · Reportes
        Route::get('/reportes/existencias',          [ReporteController::class, 'existencias']);
        Route::get('/reportes/consumo',              [ReporteController::class, 'consumo']);
        Route::get('/reportes/mermas',               [ReporteController::class, 'mermas']);
        Route::get('/reportes/kardex/{producto}',    [ReporteController::class, 'kardex']);
        Route::get('/reportes/rendimiento',          [ReporteController::class, 'rendimiento']);
    });
});
