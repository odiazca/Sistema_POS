-- ============================================================
--  SYS_TIENDA · Sistema POS multi-sucursal
--  Esquema completo (MySQL / MariaDB)  ·  utf8mb4
-- ============================================================
CREATE DATABASE IF NOT EXISTS sys_tienda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sys_tienda;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS detalle_ventas, ventas, detalle_compras, compras, movimientos_inventario,
  movimientos_caja, cajas, gastos, productos, categorias, marcas, clientes, proveedores,
  usuarios, roles, configuracion, series_comprobantes, pagos_clientes, sucursales, stock_sucursal,
  traspasos, detalle_traspasos, cotizaciones, detalle_cotizaciones, devoluciones, detalle_devoluciones,
  promociones, movimientos_puntos, auditoria;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Sucursales ----------
CREATE TABLE sucursales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  direccion VARCHAR(200) NULL,
  telefono VARCHAR(30) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------- Seguridad ----------
CREATE TABLE roles (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE,
  descripcion VARCHAR(120) NULL
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol_id TINYINT UNSIGNED NOT NULL,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  nombres VARCHAR(80) NOT NULL,
  apellidos VARCHAR(80) NOT NULL,
  usuario VARCHAR(40) NOT NULL UNIQUE,
  email VARCHAR(120) NULL,
  password VARCHAR(255) NOT NULL,
  avatar VARCHAR(120) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL,
  ultimo_acceso DATETIME NULL,
  cambiar_clave TINYINT(1) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rol_id) REFERENCES roles(id),
  FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB;

-- ---------- Catálogo ----------
CREATE TABLE categorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  icono VARCHAR(40) NOT NULL DEFAULT 'bi-tag',
  color VARCHAR(9) NOT NULL DEFAULT '#6366f1',
  estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE marcas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT UNSIGNED NOT NULL,
  marca_id INT UNSIGNED NULL,
  codigo VARCHAR(40) NOT NULL UNIQUE,
  codigo_barras VARCHAR(40) NULL,
  nombre VARCHAR(150) NOT NULL,
  descripcion VARCHAR(255) NULL,
  unidad VARCHAR(20) NOT NULL DEFAULT 'UND',
  precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_venta DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_mayor DECIMAL(10,2) NULL,
  cantidad_mayor DECIMAL(10,2) NOT NULL DEFAULT 0,      -- desde esta cantidad aplica precio_mayor (0 = no aplica)
  stock DECIMAL(12,2) NOT NULL DEFAULT 0,               -- stock consolidado (suma de todas las sucursales)
  stock_minimo DECIMAL(12,2) NOT NULL DEFAULT 5,
  fecha_vencimiento DATE NULL,
  imagen VARCHAR(150) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (nombre), INDEX (codigo_barras),
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE stock_sucursal (
  producto_id INT UNSIGNED NOT NULL,
  sucursal_id INT UNSIGNED NOT NULL,
  stock DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (producto_id, sucursal_id),
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB;

-- ---------- Terceros ----------
CREATE TABLE clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_doc ENUM('DNI','RUC','CE','OTRO') NOT NULL DEFAULT 'DNI',
  documento VARCHAR(20) NULL,
  nombre VARCHAR(150) NOT NULL,
  telefono VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  direccion VARCHAR(200) NULL,
  fecha_nacimiento DATE NULL,
  puntos INT NOT NULL DEFAULT 0,
  limite_credito DECIMAL(10,2) NOT NULL DEFAULT 0,
  saldo_deuda DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (documento), INDEX (nombre)
) ENGINE=InnoDB;

CREATE TABLE proveedores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ruc VARCHAR(20) NULL,
  razon_social VARCHAR(150) NOT NULL,
  contacto VARCHAR(100) NULL,
  telefono VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  direccion VARCHAR(200) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------- Caja ----------
CREATE TABLE cajas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  usuario_id INT UNSIGNED NOT NULL,
  fecha_apertura DATETIME NOT NULL,
  fecha_cierre DATETIME NULL,
  monto_apertura DECIMAL(10,2) NOT NULL DEFAULT 0,
  monto_cierre DECIMAL(10,2) NULL,
  estado ENUM('ABIERTA','CERRADA') NOT NULL DEFAULT 'ABIERTA',
  observacion VARCHAR(200) NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB;

CREATE TABLE movimientos_caja (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  caja_id INT UNSIGNED NOT NULL,
  tipo ENUM('INGRESO','EGRESO') NOT NULL,
  concepto VARCHAR(150) NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (caja_id) REFERENCES cajas(id)
) ENGINE=InnoDB;

-- ---------- Ventas ----------
CREATE TABLE series_comprobantes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  tipo ENUM('TICKET','BOLETA','FACTURA') NOT NULL,
  serie VARCHAR(6) NOT NULL,
  correlativo INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE (tipo, serie),
  UNIQUE (sucursal_id, tipo)
) ENGINE=InnoDB;

CREATE TABLE ventas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  caja_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NULL,
  tipo_comprobante ENUM('TICKET','BOLETA','FACTURA') NOT NULL DEFAULT 'TICKET',
  serie VARCHAR(6) NOT NULL,
  numero INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,          -- descuentos totales (promociones + manual + puntos + cumpleaños)
  igv DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  puntos_ganados INT NOT NULL DEFAULT 0,
  puntos_canjeados INT NOT NULL DEFAULT 0,
  cotizacion_id INT UNSIGNED NULL,
  metodo_pago ENUM('EFECTIVO','TARJETA','YAPE','PLIN','TRANSFERENCIA','CREDITO') NOT NULL DEFAULT 'EFECTIVO',
  monto_recibido DECIMAL(10,2) NULL,
  vuelto DECIMAL(10,2) NULL,
  estado ENUM('COMPLETADA','ANULADA','PENDIENTE') NOT NULL DEFAULT 'COMPLETADA',
  INDEX (fecha), INDEX (estado), INDEX (sucursal_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  FOREIGN KEY (caja_id) REFERENCES cajas(id) ON DELETE SET NULL,
  FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB;

CREATE TABLE detalle_ventas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venta_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  costo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,          -- descuento de la línea (promoción)
  promo VARCHAR(80) NULL,
  subtotal DECIMAL(10,2) NOT NULL,                     -- cantidad*precio - descuento
  FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- ---------- Cotizaciones ----------
CREATE TABLE cotizaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  usuario_id INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NULL,
  fecha DATE NOT NULL,
  vigencia DATE NOT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  nota VARCHAR(200) NULL,
  estado ENUM('PENDIENTE','ACEPTADA','ANULADA') NOT NULL DEFAULT 'PENDIENTE',
  venta_id INT UNSIGNED NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE detalle_cotizaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cotizacion_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- ---------- Devoluciones ----------
CREATE TABLE devoluciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  venta_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total DECIMAL(10,2) NOT NULL,
  motivo VARCHAR(200) NOT NULL,
  reintegra_stock TINYINT(1) NOT NULL DEFAULT 1,
  reembolso ENUM('EFECTIVO','MISMO_MEDIO','CREDITO') NOT NULL DEFAULT 'EFECTIVO',
  FOREIGN KEY (venta_id) REFERENCES ventas(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE detalle_devoluciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  devolucion_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  costo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (devolucion_id) REFERENCES devoluciones(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- ---------- Promociones y puntos ----------
CREATE TABLE promociones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  tipo ENUM('PORCENTAJE','MONTO','NXM') NOT NULL,         -- % de descuento, S/ por unidad, o lleva N paga M
  alcance ENUM('PRODUCTO','CATEGORIA','TODO') NOT NULL DEFAULT 'PRODUCTO',
  producto_id INT UNSIGNED NULL,
  categoria_id INT UNSIGNED NULL,
  valor DECIMAL(10,2) NOT NULL DEFAULT 0,
  lleva INT NOT NULL DEFAULT 0,
  paga INT NOT NULL DEFAULT 0,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  sucursal_id INT UNSIGNED NULL,                          -- NULL = todas las sucursales
  estado TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE movimientos_puntos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  venta_id INT UNSIGNED NULL,
  puntos INT NOT NULL,
  motivo VARCHAR(100) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;

-- ---------- Compras ----------
CREATE TABLE compras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  proveedor_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  comprobante VARCHAR(40) NULL,
  fecha DATE NOT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado ENUM('RECIBIDA','PENDIENTE','ANULADA') NOT NULL DEFAULT 'RECIBIDA',
  FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB;

CREATE TABLE detalle_compras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compra_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  costo_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- ---------- Traspasos entre sucursales ----------
CREATE TABLE traspasos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  origen_id INT UNSIGNED NOT NULL,
  destino_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nota VARCHAR(150) NULL,
  FOREIGN KEY (origen_id) REFERENCES sucursales(id),
  FOREIGN KEY (destino_id) REFERENCES sucursales(id)
) ENGINE=InnoDB;

CREATE TABLE detalle_traspasos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  traspaso_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (traspaso_id) REFERENCES traspasos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- ---------- Inventario / Gastos / Auditoría / Config ----------
CREATE TABLE movimientos_inventario (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  producto_id INT UNSIGNED NOT NULL,
  tipo ENUM('ENTRADA','SALIDA','AJUSTE') NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  stock_resultante DECIMAL(12,2) NOT NULL,
  motivo VARCHAR(150) NULL,
  usuario_id INT UNSIGNED NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (sucursal_id),
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

CREATE TABLE gastos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sucursal_id INT UNSIGNED NOT NULL DEFAULT 1,
  concepto VARCHAR(150) NOT NULL,
  categoria VARCHAR(60) NOT NULL DEFAULT 'General',
  monto DECIMAL(10,2) NOT NULL,
  fecha DATE NOT NULL,
  usuario_id INT UNSIGNED NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE pagos_clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  caja_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  monto DECIMAL(10,2) NOT NULL,
  metodo ENUM('EFECTIVO','TARJETA','YAPE','PLIN','TRANSFERENCIA') NOT NULL DEFAULT 'EFECTIVO',
  nota VARCHAR(150) NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;

CREATE TABLE auditoria (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id INT UNSIGNED NULL,
  usuario VARCHAR(40) NULL,
  sucursal_id INT UNSIGNED NULL,
  accion VARCHAR(40) NOT NULL,
  entidad VARCHAR(40) NULL,
  entidad_id INT UNSIGNED NULL,
  detalle VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  INDEX (fecha), INDEX (usuario_id), INDEX (accion)
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  clave VARCHAR(60) PRIMARY KEY,
  valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ia_consultas (
  id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, pregunta VARCHAR(500) NOT NULL, titulo VARCHAR(160) NULL,
  sql_texto TEXT NOT NULL, grafico VARCHAR(255) NULL, favorito TINYINT(1) NOT NULL DEFAULT 0, veces INT NOT NULL DEFAULT 1,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP, ultimo_uso DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX (usuario_id, favorito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Datos base ----------
INSERT INTO sucursales (id, nombre, direccion, telefono) VALUES
 (1,'Sucursal Central','Av. Principal 123, Lima','999 888 777'),
 (2,'Sucursal Norte','Jr. Los Olivos 456, Lima','999 111 222');

INSERT INTO roles (id, nombre, descripcion) VALUES
 (1,'Administrador','Acceso total al sistema y a todas las sucursales'),
 (2,'Cajero','Ventas y caja de su sucursal'),
 (3,'Almacenero','Inventario y compras de su sucursal');

INSERT INTO configuracion (clave, valor) VALUES
 ('empresa_nombre','Bodega Don Vito'),
 ('empresa_ruc','20601234567'),
 ('empresa_direccion','Av. Principal 123, Lima'),
 ('empresa_telefono','999 888 777'),
 ('empresa_razon_social',''),
 ('empresa_email',''),
 ('empresa_web',''),
 ('empresa_eslogan',''),
 ('empresa_logo',''),
 ('moneda','S/'),
 ('moneda_codigo','PEN'),
 ('moneda_decimales','2'),
 ('sep_decimal','.'),
 ('sep_miles',','),
 ('moneda_posicion','antes'),
 ('impuesto_nombre','IGV'),
 ('zona_horaria','America/Lima'),
 ('filas_pagina','12'),
 ('vencimiento_dias','30'),
 ('stock_minimo_defecto','5'),
 ('ticket_logo','1'),
 ('igv','18'),
 ('meta_diaria','2500'),
 ('ticket_mensaje','¡Gracias por su compra! Vuelva pronto.'),
 ('puntos_cada','10'),
 ('puntos_valor','0.10'),
 ('cumple_descuento','5'),
 ('print_modo','navegador'),
 ('print_ancho','80'),
 ('print_codepage','19'),
 ('print_cajon','0'),
 ('print_auto','0'),
 ('backup_auto','1'),
 ('backup_horas','24'),
 ('backup_conservar','14'),
 ('sesion_minutos','480'),
 ('ia_proveedor','gemini'),
 ('ia_url','https://generativelanguage.googleapis.com/v1beta/openai'),
 ('ia_modelo','gemini-3.5-flash-lite'),
 ('ia_resumen','1');

INSERT INTO series_comprobantes (sucursal_id, tipo, serie, correlativo) VALUES
 (1,'TICKET','T001',0),(1,'BOLETA','B001',0),(1,'FACTURA','F001',0),
 (2,'TICKET','T002',0),(2,'BOLETA','B002',0),(2,'FACTURA','F002',0);
