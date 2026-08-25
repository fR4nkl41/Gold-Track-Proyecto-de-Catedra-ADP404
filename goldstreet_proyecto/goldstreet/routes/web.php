<?php

use Illuminate\Support\Facades\Route;

/*
 | Vistas Blade (resources/views). Solo entregan la estructura de la página:
 | los datos los pide el JS de cada vista a la API (/api/...) con el token Bearer.
 | El control de acceso real lo hace la API (auth:sanctum + EnsureRole).
 */
Route::redirect('/', '/login');

Route::view('/login', 'auth.login')->name('login');
Route::view('/dashboard', 'dashboard');
Route::view('/inventario', 'inventario');
Route::view('/movimientos', 'movimientos');

// Fase 2
Route::view('/facturas', 'facturas');          // admin y bodega
Route::view('/produccion', 'produccion');      // todos consultan; admin y cocina registran

// Fase 3
Route::view('/recetas', 'recetas');                // todos consultan; costos y edición solo admin
Route::view('/compras', 'compras');                // lista sugerida y órdenes: admin y bodega
Route::view('/cuentas-por-pagar', 'cuentas');      // solo admin

// Solo admin (la vista redirige si el rol no corresponde; la API responde 403)
Route::view('/reportes', 'reportes');
Route::view('/catalogos', 'catalogos');
Route::view('/usuarios', 'usuarios');
Route::view('/proveedores/{proveedor}', 'proveedor')->whereNumber('proveedor');

// Compatibilidad con las URLs del prototipo en HTML estático
Route::redirect('/login.html', '/login', 301);
Route::redirect('/dashboard.html', '/dashboard', 301);
Route::redirect('/inventario.html', '/inventario', 301);
Route::redirect('/movimientos.html', '/movimientos', 301);
