# Entorno Docker local

Este entorno conserva la estructura legada del proyecto:

- Frontend: http://localhost:8080/frontend/
- Backend: http://localhost:8080/backend/
- MySQL desde el host: localhost:3307
- MySQL entre contenedores: db:3306

## 1. Preparar variables

Desde PowerShell, en la raíz del repositorio:

```powershell
Copy-Item .env.example .env
```

El archivo `.env` está ignorado por Git.

## 2. Construir y levantar

```powershell
docker compose up -d --build
```

La primera inicialización de MySQL:

1. crea la base configurada en `DB_NAME`;
2. importa `ecommerce.sql`;
3. ejecuta `database/migrations/20260921_001_reglas_fiscales.sql`.

Los scripts de inicialización de MySQL solo se ejecutan cuando el volumen de datos está vacío.

## 3. Ver estado

```powershell
docker compose ps
```

## 4. Ver logs

Aplicación:

```powershell
docker compose logs -f app
```

Base de datos:

```powershell
docker compose logs -f db
```

## 5. Verificar PHP y extensiones

```powershell
docker compose exec app php -v
docker compose exec app php -m
```

## 6. Probar MySQL

```powershell
docker compose exec db mysql -uecommerce -pecommerce ecommerce -e "SHOW TABLES;"
```

Si cambiaste usuario, password o base en `.env`, usa esos valores.

## 7. Reiniciar sin borrar datos

```powershell
docker compose down
docker compose up -d
```

## 8. Reiniciar desde cero

Atención: esto elimina la base local y los uploads guardados en los volúmenes Docker.

```powershell
docker compose down -v
docker compose up -d --build
```

## Nota sobre el dump legado

El dump original es de MariaDB/PHP antiguos y contiene fechas cero. El propio archivo configura su sesión SQL de importación antes de insertar los datos. MySQL vuelve a usar su modo normal para las conexiones posteriores.


## Login con Microsoft (Hotmail / Outlook)

La integración queda deshabilitada por defecto.

Para desarrollo local, registrar en Microsoft Entra una aplicación web y usar exactamente este Redirect URI:

```text
http://localhost:8080/frontend/microsoft-callback.php
```

Después completar en el archivo local `.env` las variables `MICROSOFT_OAUTH_ENABLED`, `MICROSOFT_TENANT`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET` y `MICROSOFT_REDIRECT_URI`.

El secreto nunca debe subirse al repositorio. Tras cambiar variables de entorno:

```powershell
docker compose up -d --force-recreate app
```
