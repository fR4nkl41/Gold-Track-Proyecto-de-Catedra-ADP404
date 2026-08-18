<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GoldStreetSeeder extends Seeder
{
    public function run(): void
    {
        // Usuarios (contraseñas hasheadas con bcrypt en tiempo de siembra)
        DB::table('usuarios')->insert([
            ['nombre' => 'Administrador',       'correo' => 'admin@goldstreet.com',  'contrasena' => Hash::make('admin123'),  'rol' => 'admin',  'activo' => 1],
            ['nombre' => 'Encargado de Bodega', 'correo' => 'bodega@goldstreet.com', 'contrasena' => Hash::make('bodega123'), 'rol' => 'bodega', 'activo' => 1],
            ['nombre' => 'Chef de Cocina',      'correo' => 'cocina@goldstreet.com', 'contrasena' => Hash::make('cocina123'), 'rol' => 'cocina', 'activo' => 1],
        ]);

        DB::table('categorias')->insert([
            ['id' => 1, 'nombre' => 'Carnes',    'descripcion' => 'Proteínas cárnicas frescas'],
            ['id' => 2, 'nombre' => 'Lácteos',   'descripcion' => 'Lácteos y derivados'],
            ['id' => 3, 'nombre' => 'Vegetales', 'descripcion' => 'Verduras y hortalizas frescas'],
            ['id' => 4, 'nombre' => 'Abarrotes', 'descripcion' => 'Secos, aceites y harinas'],
            ['id' => 5, 'nombre' => 'Bebidas',   'descripcion' => 'Bebidas para el restaurante'],
            ['id' => 6, 'nombre' => 'Preparados', 'descripcion' => 'Porciones listas para cocina (salen de Producción)'],
        ]);

        DB::table('proveedores')->insert([
            ['id' => 1, 'nombre' => 'Carnes El Roble',        'contacto' => 'Mario Ortiz',   'telefono' => '2222-1001', 'correo' => 'ventas@elroble.com'],
            ['id' => 2, 'nombre' => 'Lácteos Santa Clara',    'contacto' => 'Ana de Ramos',  'telefono' => '2222-1002', 'correo' => 'pedidos@santaclara.com'],
            ['id' => 3, 'nombre' => 'Distribuidora El Istmo', 'contacto' => 'Luis Pérez',    'telefono' => '2222-1003', 'correo' => 'ventas@elistmo.com'],
            ['id' => 4, 'nombre' => 'Pollo Indio',            'contacto' => 'Rosa Méndez',   'telefono' => '2222-1004', 'correo' => 'pedidos@polloindio.com'],
            ['id' => 5, 'nombre' => 'Producción interna (cocina)', 'contacto' => 'Jefe de cocina', 'telefono' => null, 'correo' => null],   // origen de los preparados: no se compran
        ]);

        // Catálogo demo (los movimientos NO se siembran a propósito:
        // se registran desde la UI como evidencia de la demo del video).
        $productos = [
            ['codigo' => 'GS-001', 'nombre' => 'Lomo de Aguja',    'categoria_id' => 1, 'proveedor_id' => 1, 'unidad' => 'kg',     'stock' => 24.5, 'stock_minimo' => 10, 'costo' => 9.50],
            ['codigo' => 'GS-002', 'nombre' => 'Crema Láctea',     'categoria_id' => 2, 'proveedor_id' => 2, 'unidad' => 'L',      'stock' => 12,   'stock_minimo' => 6,  'costo' => 2.75],
            ['codigo' => 'GS-003', 'nombre' => 'Tomate Manzano',   'categoria_id' => 3, 'proveedor_id' => 3, 'unidad' => 'kg',     'stock' => 8.5,  'stock_minimo' => 8,  'costo' => 1.90],
            ['codigo' => 'GS-004', 'nombre' => 'Pechuga de Pollo', 'categoria_id' => 1, 'proveedor_id' => 1, 'unidad' => 'kg',     'stock' => 18,   'stock_minimo' => 12, 'costo' => 5.25],
            ['codigo' => 'GS-005', 'nombre' => 'Queso Mozarella',  'categoria_id' => 2, 'proveedor_id' => 2, 'unidad' => 'kg',     'stock' => 6.5,  'stock_minimo' => 5,  'costo' => 6.80],
            ['codigo' => 'GS-006', 'nombre' => 'Aceite Vegetal',   'categoria_id' => 4, 'proveedor_id' => 3, 'unidad' => 'L',      'stock' => 15,   'stock_minimo' => 10, 'costo' => 4.20],
            ['codigo' => 'GS-007', 'nombre' => 'Harina de Trigo',  'categoria_id' => 4, 'proveedor_id' => 3, 'unidad' => 'kg',     'stock' => 30,   'stock_minimo' => 15, 'costo' => 0.85],
            ['codigo' => 'GS-008', 'nombre' => 'Papas',            'categoria_id' => 3, 'proveedor_id' => 3, 'unidad' => 'kg',     'stock' => 0,    'stock_minimo' => 9,  'costo' => 1.10],
            // Fase 2 · Producción: materia prima (lb, como en el cuaderno) y las porciones que salen de ella
            ['codigo' => 'GS-009', 'nombre' => 'Puyazo Premium',          'categoria_id' => 1, 'proveedor_id' => 1, 'unidad' => 'lb',  'stock' => 21,  'stock_minimo' => 8,  'costo' => 4.80],
            ['codigo' => 'GS-010', 'nombre' => 'Lomo Rollizo',            'categoria_id' => 1, 'proveedor_id' => 1, 'unidad' => 'lb',  'stock' => 59,  'stock_minimo' => 20, 'costo' => 3.90],
            ['codigo' => 'GS-011', 'nombre' => 'Orden de puyazo',         'categoria_id' => 6, 'proveedor_id' => 5, 'unidad' => 'und', 'stock' => 0,   'stock_minimo' => 10, 'costo' => 2.45, 'es_preparado' => 1],
            ['codigo' => 'GS-012', 'nombre' => 'Parrillada de lomo 8 oz', 'categoria_id' => 6, 'proveedor_id' => 5, 'unidad' => 'und', 'stock' => 0,   'stock_minimo' => 6,  'costo' => 2.10, 'es_preparado' => 1],
            ['codigo' => 'GS-013', 'nombre' => 'Puntas de lomo',          'categoria_id' => 6, 'proveedor_id' => 5, 'unidad' => 'und', 'stock' => 0,   'stock_minimo' => 4,  'costo' => 1.50, 'es_preparado' => 1],
        ];

        foreach ($productos as $p) {
            DB::table('productos')->insert($p);
        }

        // Fase 3 · Menú con recetas (cantidades por plato, en la unidad de cada producto)
        $id = fn (string $codigo) => DB::table('productos')->where('codigo', $codigo)->value('id');
        $platos = [
            ['Puyazo a la parrilla', 'Platos fuertes', 9.95, 'Orden de puyazo con papas fritas y ensalada', [
                ['GS-011', 1], ['GS-008', 0.2], ['GS-006', 0.03], ['GS-003', 0.05],
            ]],
            ['Parrillada de lomo', 'Platos fuertes', 11.50, 'Lomo rollizo 8 oz con puntas, papas y chimol', [
                ['GS-012', 1], ['GS-013', 1], ['GS-008', 0.2], ['GS-003', 0.08], ['GS-006', 0.03],
            ]],
            ['Pechuga a la plancha', 'Platos fuertes', 7.50, 'Pechuga con crema y papas', [
                ['GS-004', 0.25], ['GS-002', 0.05], ['GS-008', 0.2], ['GS-006', 0.02],
            ]],
            ['Pasta Alfredo', 'Pastas', 6.75, 'Pasta casera en salsa de crema y mozzarella', [
                ['GS-007', 0.15], ['GS-002', 0.12], ['GS-005', 0.08],
            ]],
        ];
        foreach ($platos as [$nombre, $categoria, $precio, $descripcion, $receta]) {
            $platoId = DB::table('platos')->insertGetId([
                'nombre' => $nombre, 'categoria' => $categoria, 'precio_venta' => $precio,
                'descripcion' => $descripcion, 'activo' => 1, 'created_at' => now(),
            ]);
            foreach ($receta as [$codigo, $cantidad]) {
                DB::table('receta_ingredientes')->insert([
                    'plato_id' => $platoId, 'producto_id' => $id($codigo), 'cantidad' => $cantidad,
                ]);
            }
        }
    }
}
