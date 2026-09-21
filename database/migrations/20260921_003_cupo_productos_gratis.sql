-- Cupo configurable para productos gratuitos.
-- NULL significa "sin limite". Para la demo legacy se asignan 100 unidades
-- a los productos cuyo precio efectivo es 0.

ALTER TABLE productos
    ADD COLUMN cupoGratis INT UNSIGNED NULL AFTER ventasGratis;

UPDATE productos
SET cupoGratis = 100
WHERE precio = 0
  AND precioOferta = 0
  AND cupoGratis IS NULL;
