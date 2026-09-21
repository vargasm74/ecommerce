-- Reglas fiscales configurables por pais, region y tipo de producto.
-- No contiene tasas legales hardcodeadas: migra la configuracion heredada existente
-- de la tabla comercio para conservar el comportamiento actual.

CREATE TABLE IF NOT EXISTS impuestos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pais CHAR(2) NOT NULL,
    region VARCHAR(100) NOT NULL DEFAULT '*',
    tipo_producto VARCHAR(30) NOT NULL DEFAULT '*',
    porcentaje DECIMAL(7,4) NOT NULL,
    fecha_desde DATE NOT NULL,
    fecha_hasta DATE NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    descripcion VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_impuestos_busqueda (pais, region, tipo_producto, activo, fecha_desde, fecha_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO impuestos (
    pais,
    region,
    tipo_producto,
    porcentaje,
    fecha_desde,
    fecha_hasta,
    activo,
    descripcion
)
SELECT
    UPPER(TRIM(c.pais)),
    '*',
    '*',
    CAST(c.impuesto AS DECIMAL(7,4)),
    CURRENT_DATE,
    NULL,
    1,
    'Configuracion heredada desde comercio.impuesto'
FROM comercio c
WHERE TRIM(c.pais) <> ''
  AND c.impuesto >= 0
  AND NOT EXISTS (
      SELECT 1
      FROM impuestos i
      WHERE i.pais = UPPER(TRIM(c.pais))
        AND i.region = '*'
        AND i.tipo_producto = '*'
        AND i.activo = 1
  );
