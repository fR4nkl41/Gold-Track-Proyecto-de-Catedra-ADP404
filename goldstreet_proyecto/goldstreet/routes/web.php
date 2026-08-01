<?php

use Illuminate\Support\Facades\Route;

/*
 | Vistas Blade (resources/views). Solo entregan la estructura de la página:
 | los datos los pide el JS de cada vista a la API (/api/...) con el token Bearer.
 | El control de acceso real lo hace la API (auth:sanctum + EnsureRole).
 */
Route::redirect('/', '/login');