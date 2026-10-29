# GoldTrack · Sistema de Control de Inventarios
**Restaurante Gold Street** — Proyecto de Administración de Proyectos (UDB)
Autenticación con roles, inventario, movimientos con trigger, mermas, dashboard con gráfico,
reportes (Sprint 2), gestión de usuarios y catálogos, respaldo de la base de datos.
**Fase 2:** facturas de compra, ficha de proveedor con precios, producción/porcionado y reporte de rendimiento.
**Fase 3:** recetas y costo por plato, lista de compras sugerida y órdenes de compra, cuentas por pagar.

## Stack
- **Laravel 12 + Sanctum**: API REST con tokens Bearer (`routes/api.php`)
- **Vistas Blade** por componentes (`resources/views`) + Tailwind (CDN) + JavaScript (fetch)
- **MySQL/MariaDB** creada con **migraciones** (5 entidades + tokens + trigger `trg_mov_stock`)
- Gráficos: Chart.js (CDN) · Identidad del logo de Gold Street: ocre `#C2621A` y dorado `#E3A21A` (principales),
  negro `#1C1713` (secundario), crema `#FBF6EE` de fondo · Inter + Playfair Display · logo en `public/img/`

## Cómo correr
Primera vez:
```bash
composer install                                   # crea vendor/
copy .env.example .env  &&  php artisan key:generate   # solo si no existe .env
# en phpMyAdmin: Nueva → base "goldstreet", cotejamiento utf8mb4_unicode_ci (el .env.example ya apunta a MySQL)
php artisan migrate --seed                         # tablas + trigger + usuarios demo + catálogo
```
Alternativa sin migraciones: importar `database/goldstreet_schema.sql` en phpMyAdmin y luego `php artisan db:seed`.

> **Si la BD ya existía** (importada con una versión anterior del SQL), no corras `migrate` encima:
> haz un respaldo y recréala con `php artisan migrate:fresh --seed` (⚠ borra los datos actuales)
> o vuelve a importar el `goldstreet_schema.sql` actualizado.

Arrancar:
```bash
php artisan serve
# abre http://127.0.0.1:8000  (redirige a /login)
```

Pruebas automáticas (SQLite en memoria, no tocan la BD real):
```bash
php artisan test
```

## Usuarios demo
| Rol           | Correo                 | Contraseña |
|---------------|------------------------|------------|
| Administrador | admin@goldstreet.com   | admin123   |
| Bodega        | bodega@goldstreet.com  | bodega123  |
| Cocina        | cocina@goldstreet.com  | cocina123  |

Los usuarios y contraseñas se administran desde la vista **Usuarios** (rol admin).

Todos los roles entran por el mismo `/login`: el rol lo define la cuenta, no la pantalla. El selector
"Cuenta demo" y el texto con las contraseñas solo aparecen con `APP_ENV=local`; con `APP_ENV=production`
el login muestra únicamente correo y contraseña.

## Matriz de permisos (sección 4.4 del documento)
| Acción                                   | admin | bodega | cocina |
|------------------------------------------|:-----:|:------:|:------:|
| Consultar dashboard, inventario, kardex  |  sí   |   sí   |   sí   |
| Registrar ENTRADA                        |  sí   |   sí   |   no   |
| Registrar SALIDA (consumo / despacho)    |  sí   |   sí   |   sí   |
| Registrar MERMA (pérdida)                |  sí   |   sí   |   sí   |
| Registrar y consultar FACTURAS de compra |  sí   |   sí   |   no   |
| Escribir precios al registrar una factura |  sí   |   sí   |   no   |
| Consultar precios, totales y ficha de proveedor |  sí   |   no   |   no   |
| Anular facturas                          |  sí   |   no   |   no   |
| Registrar PRODUCCIÓN (porcionado)        |  sí   |   no   |   sí   |
| Consultar producción                     |  sí   |   sí   |   sí   |
| Consultar recetas (ficha técnica)        |  sí   |   sí   |   sí   |
| Ver costos de platos y administrar menú  |  sí   |   no   |   no   |
| Lista de compras y órdenes de compra     |  sí   |   sí   |   no   |
| Cuentas por pagar y pagos                |  sí   |   no   |   no   |
| CRUD de productos (alta/edición/baja)    |  sí   |   no   |   no   |
| Catálogos, usuarios y reportes           |  sí   |   no   |   no   |

El backend aplica la matriz (HTTP 403) aunque se use Postman; la UI además oculta lo que el rol no puede usar.
Bodega escribe el precio de cada línea al registrar la factura, pero después no lo ve: la API le devuelve
montos vacíos y la ficha del proveedor le responde 403 (pruebas `test_bodega_registra_factura_y_sube_stock_y_costo`
y `test_ficha_del_proveedor_es_solo_para_admin`). Catálogos: todos los consultan (filtros); solo el admin los edita.
Bodega registra entradas y salidas, como indica la sección 2 del documento (ej. despacho del almacén a cocina).

## Mermas
Una merma es una **salida con causa** (`vencido`, `dañado`, `mal preparado`, `empaque dañado`
— bolsa rota, lata golpeada —, `desperdicio` — recortes y sobras de la preparación —, `otro`):
descuenta stock igual que una salida (mismo trigger), pero se reporta aparte como **pérdida**.
La registra quien la detecta (bodega o cocina). Aparece en el dashboard ("Mermas del mes" y en el
gráfico), en el kardex, en el filtro de movimientos y en el reporte **Mermas** (por producto y causa).

## Fase 2 (expansión inspirada en los cuadernos del restaurante)
**Facturas de compra** (`/facturas`, admin y bodega). Bodega registra proveedor, número, fecha y productos
con cantidad y precio. Al guardar, cada línea se registra como ENTRADA (referencia "Factura 00981 · Proveedor")
y el trigger sube el stock; el costo del producto pasa a ser el último precio de compra.
- La misma factura del mismo proveedor no se registra dos veces.
- Bodega no ve montos ni precios en listados y detalles; el admin sí.
- Anular (admin): no se borra; se registran salidas que revierten las entradas. Si parte de lo comprado ya
  se usó, no se permite (el stock quedaría negativo).

**Ficha de proveedor** (`/proveedores/{id}`, solo admin, botón "Ficha" en Catálogos): contacto, total comprado,
compras del mes, productos que se le compran con último precio (y si subió o bajó), promedio, mínimo y máximo,
y sus facturas. Todo sale de las facturas registradas.

**Producción / porcionado** (`/produccion`, registran admin y cocina): "se partió 21 lb de puyazo, salieron
41 órdenes y 0.64 de desperdicio". Genera tres movimientos ligados a la producción:
1. SALIDA de la materia prima aprovechada (no cuenta como consumo, se transformó);
2. MERMA con causa *desperdicio*;
3. ENTRADA de cada porción u orden que salió (admite varios resultados: parrilladas + puntas).

Además recalcula el **costo de cada porción** = cantidad usada × costo de la materia prima (desperdicio incluido)
÷ porciones obtenidas (21 lb × $4.80 ÷ 41 = $2.46), así el costo del plato sigue al precio real de la carne.
Si el rendimiento supera el doble del promedio de esa materia prima (o 10 por unidad sin historial), la API
responde 422 con `requiere_confirmacion` y la pantalla pide confirmar: evita porciones fantasma por un error de tipeo.
Los preparados tienen como proveedor "Producción interna (cocina)": no se compran a nadie.

**Reporte de rendimiento** (pestaña en Reportes): por materia prima, cuánto se usó, cuánto se desperdició,
% de aprovechamiento, cuántas porciones rinde por unidad (ej. 1.95 órdenes por lb) y su costo.

> Consumo real = salidas sin causa que no vienen de una producción ni de anular una factura
> (`MovimientoInventario::es_consumo`). Así la carne y sus porciones no se cuentan dos veces.

## Fase 3
**Recetas y costo por plato** (`/recetas`). Cada plato del menú con sus ingredientes (en la unidad de cada
producto: 0.25 kg de pechuga, 1 und de orden de puyazo). El costo se calcula con el costo actual de cada
ingrediente, así que sube solo cuando una factura trae un precio mayor. El admin ve costo, margen y
**food cost** (% del precio que se va en ingredientes: verde ≤ 30 %, amarillo ≤ 40 %, rojo más).
Todos ven cuántos platos alcanzan con el stock actual. Cocina ve la receta sin costos.

**Lista de compras sugerida y órdenes de compra** (`/compras`, admin y bodega).
1. Entran los productos en su stock mínimo o debajo, agrupados por proveedor principal.
2. Cantidad sugerida = stock ideal (`stock_maximo`, o 2 × mínimo si no se definió) − stock − lo que ya viene
   en órdenes pendientes. Se puede ajustar antes de generar la orden.
3. Se genera una orden por proveedor (OC-00001…), que se imprime sin precios para el proveedor.
4. Al llegar el pedido, "Recibir" abre la factura con las líneas de la orden; se corrige a lo que realmente
   llegó y la orden guarda pedido contra recibido (faltantes).
Las porciones hechas en cocina (`es_preparado`) no se compran: salen aparte como "por preparar".

**Cuentas por pagar** (`/cuentas-por-pagar`, solo admin). La factura se registra de contado o al crédito
con fecha de vencimiento. Muestra total por pagar, vencido, lo que vence en 7 días y saldo por proveedor;
se registran abonos parciales o totales (efectivo, transferencia, cheque). Una factura con pagos no se
puede anular. El dashboard del admin avisa cuando hay pagos vencidos o por vencer.

## Vistas
| URL            | Vista                                                        | Acceso |
|----------------|--------------------------------------------------------------|--------|
| `/login`       | Inicio de sesión                                             | público |
| `/dashboard`   | Indicadores, gráfico semanal, inventario reciente, críticos, accesos rápidos | todos |
| `/inventario`  | Productos con filtros + alta/edición/baja                    | todos (edición admin) |
| `/movimientos` | Kardex con filtros (tipo, producto, fechas) + entradas, salidas y mermas | todos |
| `/facturas`    | Facturas de compra: registro con líneas, detalle, anulación  | admin, bodega |
| `/produccion`  | Porcionado/cocción con desperdicio y aprovechamiento        | todos (registro admin, cocina) |
| `/proveedores/{id}` | Ficha del proveedor: productos, precios y facturas      | admin  |
| `/recetas`     | Recetas, costo por plato, food cost y platos posibles        | todos (costos admin) |
| `/compras`     | Lista de compras sugerida y órdenes de compra                | admin, bodega |
| `/cuentas-por-pagar` | Saldos con proveedores y registro de pagos            | admin  |
| `/reportes`    | Existencias · Consumo · Mermas · Rendimiento · Kardex (CSV / imprimir) | admin |
| `/catalogos`   | Categorías y proveedores                                     | admin  |
| `/usuarios`    | Usuarios y roles                                             | admin  |

### Componentes Blade (`resources/views/components`)
Cada pieza es independiente y se usa solo donde hace falta:

| Componente | Uso |
|---|---|
| `<x-layouts.base>` | Esqueleto HTML (head + scripts). Sin navegación — lo usa el login |
| `<x-layouts.app>` | Plantilla autenticada; compone sidebar/topbar/footer. Se apagan con `:sidebar="false"`, `:topbar="false"`, `:footer="false"`; `acceso="admin"` restringe la página |
| `<x-head>` | Meta, Tailwind, fuentes y clases `gs-input`, `gs-btn-*` |
| `<x-sidebar>` / `<x-sidebar-link>` | Barra lateral con accesos por rol |
| `<x-topbar>` | Encabezado con usuario, rol y "Salir" |
| `<x-footer>` | Pie con la versión (`APP_VERSION`) |
| `<x-page-header>` | Título + subtítulo + botones de acción |
| `<x-card>` | Panel con título y slot de acciones |
| `<x-stat-card>` | Tarjeta de indicador |
| `<x-data-table>` | Tabla con encabezados; el JS pinta el `<tbody>` |
| `<x-modal>` | Modal con encabezado negro y formulario, Cancelar / Guardar (cierra con Esc o clic fuera) |
| `<x-confirmar>` | Diálogo de confirmación de la app (`await App.confirmar({...})`), reemplaza `confirm()` |
| `<x-field>` | Etiqueta + campo + ayuda |

Cualquier elemento con `data-roles="admin,bodega"` solo se muestra a esos roles.

### Detalles de diseño
- **Tablets** (bodega y cocina): menú plegable con botón ☰ y, en pantallas táctiles, botones y campos de 44 px de alto.
- **Carga y estados vacíos:** las tablas muestran filas grises mientras llega la API (`App.filasCargando`) y un
  mensaje con sugerencia cuando no hay datos (`App.filaVacia(cols, texto, sugerencia)`).
- **Reportes impresos / PDF:** encabezado con el logo, nombre del reporte, fecha y quién lo generó; pie con la versión.
- **Ícono de la pestaña:** `public/img/favicon.png` (también `apple-touch-icon.png` para tablets).

## Endpoints
| Método | Ruta                              | Descripción                                        |
|--------|-----------------------------------|----------------------------------------------------|
| POST   | /api/login                        | Login → token Sanctum (máx. 5 intentos/min)        |
| GET    | /api/me                           | Usuario autenticado                                |
| POST   | /api/logout                       | Revoca el token actual                             |
| GET    | /api/dashboard                    | Resumen, entradas/salidas/mermas, inventario reciente, críticos, consumo semanal |
| GET    | /api/productos                    | Lista activa (?todos=1&categoria_id=&buscar=)      |
| GET    | /api/productos/{id}               | Detalle                                            |
| POST   | /api/productos                    | Crear (admin)                                      |
| PUT    | /api/productos/{id}               | Editar (admin)                                     |
| DELETE | /api/productos/{id}               | Baja lógica (admin)                                |
| GET    | /api/movimientos                  | Kardex (?tipo=entrada\|salida\|merma\|compra\|produccion&producto_id=&desde=&hasta=&limite=) |
| POST   | /api/movimientos                  | Registrar entrada/salida; con `causa` = merma (matriz 4.4) |
| GET    | /api/categorias · /api/proveedores | Catálogos (todos)                                 |
| POST/PUT/DELETE | /api/categorias/{id} · /api/proveedores/{id} | CRUD de catálogos (admin)  |
| GET/POST/PUT | /api/usuarios/{id}          | Gestión de usuarios (admin)                        |
| GET    | /api/reportes/existencias         | Stock y valor por producto y categoría (admin)     |
| GET    | /api/reportes/consumo             | Entradas/salidas/mermas por producto (?desde=&hasta=) (admin) |
| GET    | /api/reportes/mermas              | Pérdidas por producto y causa (?desde=&hasta=) (admin) |
| GET    | /api/reportes/kardex/{producto}   | Kardex con saldo (?desde=&hasta=) (admin)          |
| GET    | /api/reportes/rendimiento         | Rendimiento de producción por materia prima (admin) |
| GET    | /api/facturas                     | Facturas (?proveedor_id=&estado=&desde=&hasta=&buscar=) (admin, bodega) |
| GET    | /api/facturas/{id}                | Detalle con líneas (precios solo admin)            |
| POST   | /api/facturas                     | Registrar factura → entradas automáticas (admin, bodega) |
| POST   | /api/facturas/{id}/anular         | Anular con motivo, revierte el stock (admin)       |
| GET    | /api/proveedores/{id}/ficha       | Productos comprados, precios y facturas (admin)    |
| GET    | /api/producciones                 | Producciones (?producto_origen_id=&desde=&hasta=) (todos) |
| POST   | /api/producciones                 | Registrar porcionado/cocción (admin, cocina)       |
| GET    | /api/platos                       | Recetas (?todos=1); costos solo admin              |
| POST/PUT/DELETE | /api/platos/{id}         | Administrar menú y recetas (admin)                 |
| GET    | /api/compras/sugerencia           | Lista de compras por proveedor + porciones por preparar (admin, bodega) |
| GET/POST | /api/ordenes-compra             | Órdenes de compra (?estado=&proveedor_id=) (admin, bodega) |
| GET    | /api/ordenes-compra/{id}          | Detalle: pedido vs. recibido                        |
| POST   | /api/ordenes-compra/{id}/cancelar | Cancelar una orden pendiente                       |
| GET    | /api/cuentas-por-pagar            | Saldos, vencidas, por proveedor (?estado=&proveedor_id=) (admin) |
| POST   | /api/facturas/{id}/pagos          | Registrar abono (admin)                            |

## Reglas de negocio clave
1. El stock **solo** lo escribe el trigger `trg_mov_stock`: la BD es la única fuente de verdad (4.2).
2. El registro de movimientos corre en transacción con `lockForUpdate` (4.3).
3. Una salida o merma nunca deja el stock en negativo (HTTP 422). La cantidad debe ser > 0 (API + `CHECK` en BD).
   En MySQL/MariaDB además `CHECK (stock >= 0)` en productos: si algo se saltara la regla, la BD rechaza el movimiento.
4. La baja de producto es **lógica** (el historial de movimientos se conserva).
5. RF05/RF06: alerta cuando el stock es **igual o menor** al mínimo; 3 estados (óptimo / bajo mínimo / agotado).
6. El stock inicial de un producto nuevo se registra como ENTRADA "Stock inicial" en el kardex;
   al editar un producto el stock no se puede modificar (solo con movimientos).
7. Diccionario de datos (3.4): categoría y proveedor obligatorios; unidad ∈ {kg, lb, L, und, caja};
   proveedor con dirección; movimiento con referencia (factura, orden, motivo).
8. Usuarios: no se borran, se desactivan (y se cierran sus sesiones). El admin no puede quitarse su rol.
9. Categorías/proveedores con productos asignados no se pueden eliminar (HTTP 409).

## Respaldo de la base de datos (sección 2.3)
```bash
php artisan backup:db              # storage/app/backups/goldstreet_AAAAMMDD_HHMMSS.sql
php artisan backup:db --keep=14    # conserva los 14 más recientes (por defecto 7)
```
- Usa `mysqldump` (detecta el de XAMPP automáticamente; si no, define `DB_DUMP_BINARY` en el `.env`).
- Incluye el trigger `trg_mov_stock`. Restaurar: importar el .sql en phpMyAdmin, o `C:\xampp\mysql\bin\mysql.exe -u root goldstreet < archivo.sql`.
- Programado todos los días a las **23:30**. Para que corra solo, deja abierto `php artisan schedule:work`
  o crea una tarea en el Programador de tareas de Windows que ejecute `php artisan schedule:run` cada minuto.

## Despliegue (sección 2.3)
**Red local del restaurante (diagrama 2.1):** el servidor corre en una PC y las estaciones de
bodega, cocina y administración entran por Wi-Fi.
```bash
php artisan serve --host=0.0.0.0 --port=8000
# desde las otras PCs/tablets: http://IP-DEL-SERVIDOR:8000   (ipconfig muestra la IP)
```
Con Apache de XAMPP: apuntar el `DocumentRoot` (o un VirtualHost) a la carpeta `public/`.

**Nube (Render / Railway)** — pasos sugeridos:
1. Subir el repositorio y crear un servicio PHP + una base MySQL.
2. Variables de entorno: `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*`,
   `APP_TIMEZONE=America/El_Salvador`, `SESSION_DRIVER=file`, `CACHE_STORE=file`.
3. Comando de compilación: `composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan view:cache`
4. Al desplegar: `php artisan migrate --force` (y `php artisan db:seed --force` solo la primera vez).
5. El proxy HTTPS ya está contemplado (`trustProxies` en `bootstrap/app.php`).

## Pendientes del Anexo B (capturas)
- `php artisan route:list --path=api`
- Trigger `trg_mov_stock` visible en phpMyAdmin
- Pestaña Network del navegador mostrando `Authorization: Bearer ...`
- Postman: entrada con rol cocina → HTTP 403

## Estructura
```
app/Console/Commands/  BackupDb (php artisan backup:db)
app/Http/
  Controllers/  Auth · Producto · MovimientoInventario · Dashboard · Catalogo · Reporte · Usuario ·
                Factura · Produccion (fase 2) · Plato · OrdenCompra · CuentaPagar (fase 3)
  Middleware/   EnsureRole (alias 'role')
  Requests/     Login · StoreProducto · UpdateProducto · StoreMovimiento · Usuario · Categoria · Proveedor
app/Models/     Usuario · Producto · Categoria · Proveedor · MovimientoInventario ·
                Factura · FacturaDetalle · Produccion · ProduccionResultado ·
                Plato · RecetaIngrediente · OrdenCompra · OrdenCompraDetalle · PagoFactura
app/Support/    Snapshot (JSON para el frontend) · TriggerStock (SQL del trigger)
database/
  migrations/             <- tablas + CHECK + trigger + alineación con el diccionario de datos
  goldstreet_schema.sql   <- alternativa equivalente para phpMyAdmin
  seeders/GoldStreetSeeder.php
resources/views/
  components/   layouts/{base,app} · head · sidebar · sidebar-link · topbar · footer ·
                page-header · card · stat-card · data-table · modal · confirmar · field
  auth/login · dashboard · inventario · movimientos · reportes · catalogos · usuarios ·
  facturas · produccion · proveedor · recetas · compras · cuentas
public/js/
  core.js                 <- API, sesión, permisos por rol, utilidades, router
  pages/{vista}.js        <- lógica de cada vista
routes/  api.php · web.php · console.php (programación del respaldo)
tests/Feature/GoldTrackTest.php           <- 28 pruebas de reglas de negocio (fase 1)
tests/Feature/ComprasProduccionTest.php   <- 21 pruebas de facturas, ficha de proveedor y producción
tests/Feature/RecetasComprasPagosTest.php <- 18 pruebas de recetas, órdenes de compra y cuentas por pagar
```
