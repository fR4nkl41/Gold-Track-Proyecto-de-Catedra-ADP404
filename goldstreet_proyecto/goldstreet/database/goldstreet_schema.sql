-- ============================================================================
--  GoldStreet · Sistema de Control de Inventarios
--  Restaurante Gold Street — Administración de Proyectos (UDB)
--
--  Esquema de la base de datos (punto 5.4 del documento):
--    usuarios · productos · categorias · proveedores · movimientos
--    + fase 2: facturas · factura_detalles · producciones · produccion_resultados
--    + fase 3: platos · receta_ingredientes · ordenes_compra · orden_compra_detalles · pagos_factura
--    + personal_access_tokens (tokens de sesión de Laravel Sanctum)
--
--  IMPORTANTE (sección 4.2 del documento):
--    El stock de productos SOLO lo escribe el trigger trg_mov_stock:
--    la base de datos es la única fuente de verdad del inventario.
--
--  Forma recomendada de crear la BD: migraciones de Laravel
--    php artisan migrate --seed
--  Este archivo es la ALTERNATIVA equivalente para phpMyAdmin:
--    mysql -u root goldstreet < goldstreet_schema.sql   (y luego php artisan db:seed)
--    o phpMyAdmin → Importar → seleccionar este archivo.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS pagos_factura;
DROP TABLE IF EXISTS receta_ingredientes;
DROP TABLE IF EXISTS platos;
DROP TABLE IF EXISTS produccion_resultados;
DROP TABLE IF EXISTS producciones;
DROP TABLE IF EXISTS factura_detalles;
DROP TABLE IF EXISTS facturas;
DROP TABLE IF EXISTS orden_compra_detalles;
DROP TABLE IF EXISTS ordenes_compra;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS proveedores;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS personal_access_tokens;
SET FOREIGN_KEY_CHECKS = 1;

-- 1) USUARIOS ----------------------------------------------------------------
CREATE TABLE usuarios (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(100) NOT NULL,
  correo     VARCHAR(120) NOT NULL,
  contrasena VARCHAR(255) NOT NULL,
  rol        ENUM('admin','bodega','cocina') NOT NULL DEFAULT 'bodega',
  activo     TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) CATEGORIAS --------------------------------------------------------------
CREATE TABLE categorias (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) PROVEEDORES -------------------------------------------------------------
CREATE TABLE proveedores (
  id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(120) NOT NULL,
  contacto VARCHAR(120) NULL,
  telefono VARCHAR(30) NULL,
  correo   VARCHAR(120) NULL,
  direccion VARCHAR(150) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proveedores_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) PRODUCTOS ---------------------------------------------------------------
CREATE TABLE productos (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo       VARCHAR(30) NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  categoria_id BIGINT UNSIGNED NOT NULL,
  proveedor_id BIGINT UNSIGNED NOT NULL,
  unidad       ENUM('kg','lb','L','und','caja') NOT NULL DEFAULT 'kg',
  stock        DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock_minimo DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock_maximo DECIMAL(10,2) NULL,                 -- stock ideal: hasta dónde repone la lista de compras
  es_preparado TINYINT(1) NOT NULL DEFAULT 0,      -- porción hecha en cocina: no se compra
  costo        DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado       ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_productos_codigo (codigo),
  KEY fk_productos_categoria (categoria_id),
  KEY fk_productos_proveedor (proveedor_id),
  CONSTRAINT chk_productos_stock CHECK (stock >= 0),          -- red de seguridad: el trigger nunca deja negativos
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id),
  CONSTRAINT fk_productos_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) FASE 3 · ÓRDENES DE COMPRA ------------------------------------------------
CREATE TABLE ordenes_compra (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  proveedor_id   BIGINT UNSIGNED NOT NULL,
  usuario_id     BIGINT UNSIGNED NOT NULL,
  estado         ENUM('pendiente','recibida','cancelada') NOT NULL DEFAULT 'pendiente',
  fecha_entrega  DATE NULL,
  notas          VARCHAR(255) NULL,
  total_estimado DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_oc_estado (estado),
  CONSTRAINT fk_oc_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id),
  CONSTRAINT fk_oc_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orden_compra_detalles (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_compra_id   BIGINT UNSIGNED NOT NULL,
  producto_id       BIGINT UNSIGNED NOT NULL,
  cantidad          DECIMAL(10,2) NOT NULL,
  precio_estimado   DECIMAL(10,2) NOT NULL DEFAULT 0,
  cantidad_recibida DECIMAL(10,2) NULL,            -- se llena al recibir la factura
  PRIMARY KEY (id),
  CONSTRAINT chk_ocd_cantidad CHECK (cantidad > 0),
  CONSTRAINT fk_ocd_orden    FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra (id) ON DELETE CASCADE,
  CONSTRAINT fk_ocd_producto FOREIGN KEY (producto_id)     REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) FASE 2 · FACTURAS DE COMPRA Y PRODUCCIÓN ----------------------------------
-- Compras a proveedores (cada línea genera una ENTRADA) y porcionado/cocción
-- (materia prima → porciones; el desperdicio se registra como merma).
CREATE TABLE facturas (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  proveedor_id     BIGINT UNSIGNED NOT NULL,
  numero           VARCHAR(40) NOT NULL,
  fecha            DATE NOT NULL,
  condicion        ENUM('contado','credito') NOT NULL DEFAULT 'contado',   -- fase 3: cuentas por pagar
  fecha_vencimiento DATE NULL,
  orden_compra_id  BIGINT UNSIGNED NULL,           -- fase 3: orden que recibe esta factura
  usuario_id       BIGINT UNSIGNED NOT NULL,
  total            DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado           ENUM('registrada','anulada') NOT NULL DEFAULT 'registrada',
  motivo_anulacion VARCHAR(150) NULL,
  anulada_por      BIGINT UNSIGNED NULL,
  anulada_at       TIMESTAMP NULL DEFAULT NULL,
  created_at       TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_factura_proveedor_numero (proveedor_id, numero),
  KEY idx_facturas_fecha (fecha),
  CONSTRAINT fk_fac_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id),
  CONSTRAINT fk_fac_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id),
  CONSTRAINT fk_fac_anulada   FOREIGN KEY (anulada_por)  REFERENCES usuarios (id),
  CONSTRAINT fk_fac_orden     FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE factura_detalles (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  factura_id      BIGINT UNSIGNED NOT NULL,
  producto_id     BIGINT UNSIGNED NOT NULL,
  cantidad        DECIMAL(10,2) NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal        DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT chk_fd_cantidad CHECK (cantidad > 0),
  CONSTRAINT fk_fd_factura  FOREIGN KEY (factura_id)  REFERENCES facturas (id) ON DELETE CASCADE,
  CONSTRAINT fk_fd_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE producciones (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id         BIGINT UNSIGNED NOT NULL,
  producto_origen_id BIGINT UNSIGNED NOT NULL,
  cantidad_origen    DECIMAL(10,2) NOT NULL,
  desperdicio        DECIMAL(10,2) NOT NULL DEFAULT 0,
  notas              VARCHAR(150) NULL,
  created_at         TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_producciones_fecha (created_at),
  CONSTRAINT chk_prod_cantidad CHECK (cantidad_origen > 0 AND desperdicio >= 0 AND desperdicio < cantidad_origen),
  CONSTRAINT fk_prod_usuario FOREIGN KEY (usuario_id)         REFERENCES usuarios (id),
  CONSTRAINT fk_prod_origen  FOREIGN KEY (producto_origen_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produccion_resultados (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  produccion_id BIGINT UNSIGNED NOT NULL,
  producto_id   BIGINT UNSIGNED NOT NULL,
  cantidad      DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT chk_pr_cantidad CHECK (cantidad > 0),
  CONSTRAINT fk_pr_produccion FOREIGN KEY (produccion_id) REFERENCES producciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_producto   FOREIGN KEY (producto_id)   REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fase 3 · Abonos a facturas al crédito
CREATE TABLE pagos_factura (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  factura_id BIGINT UNSIGNED NOT NULL,
  monto      DECIMAL(12,2) NOT NULL,
  fecha      DATE NOT NULL,
  metodo     ENUM('efectivo','transferencia','cheque','otro') NOT NULL DEFAULT 'efectivo',
  referencia VARCHAR(60) NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT chk_pago_monto CHECK (monto > 0),
  CONSTRAINT fk_pago_factura FOREIGN KEY (factura_id) REFERENCES facturas (id) ON DELETE CASCADE,
  CONSTRAINT fk_pago_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) FASE 3 · MENÚ Y RECETAS ---------------------------------------------------
CREATE TABLE platos (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(120) NOT NULL,
  categoria    VARCHAR(60) NULL,
  precio_venta DECIMAL(10,2) NOT NULL,
  descripcion  VARCHAR(255) NULL,
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_platos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE receta_ingredientes (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plato_id    BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  cantidad    DECIMAL(10,3) NOT NULL,              -- por plato, en la unidad del producto
  PRIMARY KEY (id),
  UNIQUE KEY uq_receta_plato_producto (plato_id, producto_id),
  CONSTRAINT chk_ri_cantidad CHECK (cantidad > 0),
  CONSTRAINT fk_ri_plato    FOREIGN KEY (plato_id)    REFERENCES platos (id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) MOVIMIENTOS -------------------------------------------------------------
CREATE TABLE movimientos (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id BIGINT UNSIGNED NOT NULL,
  usuario_id  BIGINT UNSIGNED NOT NULL,
  tipo        ENUM('entrada','salida') NOT NULL,
  cantidad    DECIMAL(10,2) NOT NULL,
  referencia  VARCHAR(120) NULL,                 -- factura, orden, motivo...
  causa       ENUM('vencido','danado','mal_preparado','empaque','desperdicio','otro') NULL,  -- con valor = merma
  factura_id    BIGINT UNSIGNED NULL,               -- entrada/anulación generada por una factura
  produccion_id BIGINT UNSIGNED NULL,               -- movimiento generado por una producción
  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_mov_producto (producto_id),
  KEY fk_mov_usuario (usuario_id),
  KEY idx_mov_fecha_tipo (created_at, tipo),
  CONSTRAINT chk_mov_cantidad CHECK (cantidad > 0),
  CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT fk_mov_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id),
  CONSTRAINT fk_mov_factura  FOREIGN KEY (factura_id)  REFERENCES facturas (id),
  CONSTRAINT fk_mov_produccion FOREIGN KEY (produccion_id) REFERENCES producciones (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9) TOKENS DE SANCTUM -------------------------------------------------------
CREATE TABLE personal_access_tokens (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id   BIGINT UNSIGNED NOT NULL,
  name           VARCHAR(255) NOT NULL,
  token          VARCHAR(64) NOT NULL,
  abilities      TEXT NULL,
  last_used_at   TIMESTAMP NULL DEFAULT NULL,
  expires_at     TIMESTAMP NULL DEFAULT NULL,
  created_at     TIMESTAMP NULL DEFAULT NULL,
  updated_at     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY personal_access_tokens_token_unique (token),
  KEY personal_access_tokens_tokenable_index (tokenable_type, tokenable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10) TRIGGER DE STOCK (sección 4.2 del documento) ----------------------------
-- Única fuente de verdad: cada movimiento insertado actualiza el stock.
DELIMITER $$
CREATE TRIGGER trg_mov_stock
AFTER INSERT ON movimientos
FOR EACH ROW
BEGIN
  IF NEW.tipo = 'entrada' THEN
    UPDATE productos SET stock = stock + NEW.cantidad WHERE id = NEW.producto_id;
  ELSE
    UPDATE productos SET stock = stock - NEW.cantidad WHERE id = NEW.producto_id;
  END IF;
END$$
DELIMITER ;
