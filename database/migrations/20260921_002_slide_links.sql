-- Corrige los enlaces legacy del carrusel.
-- Los tres primeros slides apuntaban a "#" y el cuarto no tenia boton/url.

UPDATE slide
SET boton = 'VER PRODUCTO',
    url = 'zapatilla-clasica-1'
WHERE id = 1;

UPDATE slide
SET boton = 'VER PRODUCTO',
    url = 'crea-aplicaciones-con-php-55'
WHERE id = 2;

UPDATE slide
SET boton = 'VER PRODUCTO',
    url = 'telefono-movil-iphone-1'
WHERE id = 3;

UPDATE slide
SET boton = 'VER PRODUCTOS',
    url = 'telefonos-movil'
WHERE id = 4;
