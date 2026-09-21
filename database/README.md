# Reglas fiscales del checkout

Las tasas no se hardcodean en PHP ni JavaScript. Se configuran en la tabla `impuestos`.

## Prioridad de aplicación

Para un producto, el backend busca la regla activa más específica:

1. país + región + tipo de producto
2. país + región + cualquier tipo (`*`)
3. país + cualquier región (`*`) + tipo de producto
4. país + cualquier región + cualquier tipo

Si el usuario selecciona un país y no existe una regla aplicable, el checkout debe detenerse en lugar de reutilizar silenciosamente la tasa de otro país.

## Ejemplos de configuración

Los valores siguientes son solo ejemplos de estructura. El porcentaje real debe definirse con la regla fiscal vigente que corresponda.

```sql
-- Regla general de un país
INSERT INTO impuestos (
    pais, region, tipo_producto, porcentaje,
    fecha_desde, fecha_hasta, activo, descripcion
) VALUES (
    'XX', '*', '*', 0.0000,
    '2026-01-01', NULL, 1, 'Regla general'
);

-- Regla especial para productos físicos de una región
INSERT INTO impuestos (
    pais, region, tipo_producto, porcentaje,
    fecha_desde, fecha_hasta, activo, descripcion
) VALUES (
    'XX', 'REGION-1', 'fisico', 0.0000,
    '2026-01-01', NULL, 1, 'Regla regional para productos físicos'
);
```

## Vigencia

Cuando una tasa cambia no se debe modificar una compra histórica. Se crea una nueva regla con una nueva `fecha_desde` y se cierra la anterior con `fecha_hasta`.

Más adelante, cuando incorporemos la entidad de órdenes, cada orden guardará también la regla y el porcentaje efectivamente usados al momento del pago.
