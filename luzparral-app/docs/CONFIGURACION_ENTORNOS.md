# Configuración de entornos

La aplicación mantiene configuraciones separadas para desarrollo local y para
el contenedor institucional. Ningún archivo con contraseñas o claves debe quedar
versionado.

## Desarrollo local

`.env.example` es la plantilla local. Se copia como `.env` y se completa
solamente en el equipo de desarrollo. Utiliza depuración, logs en archivo y los
servicios locales de MySQL.

```powershell
Copy-Item .env.example .env
php artisan key:generate
php artisan config:clear
```

## Producción institucional

Las plantillas de `deploy/parra/` documentan las variables necesarias para
Parra. En el servidor se crean `mysql.env`, `parra-staging.env` y
`parra-production.env` con modo `600`. Los tres archivos quedan fuera de Git y
no deben incorporarse a imágenes, respaldos compartidos ni capturas.

La clave `APP_KEY` de producción debe ser distinta de la clave local. Se puede
generar sin modificar el entorno actual con:

```powershell
php artisan key:generate --show
```

El valor resultante se guarda únicamente en el archivo privado del servidor.
La primera validación utilizará el puerto 2004; al promover la versión estable,
`APP_URL` debe cambiar al puerto 2003 y se debe reconstruir la caché de
configuración.

## Valores obligatorios en producción

- `APP_ENV=production` y `APP_DEBUG=false`.
- `APP_TIMEZONE=America/Santiago` y configuración regional en español.
- `LOG_CHANNEL=stderr_json` para consultar eventos estructurados mediante
  Podman.
- `SESSION_ENCRYPT=true`, cookie HTTP-only y `SameSite=lax`.
- `QUEUE_CONNECTION=sync`, porque la versión actual no requiere un proceso
  worker separado.
- Credenciales de la instancia MySQL privada almacenadas solo en archivos con
  modo `600`. `DB_HOST=luzparral-mysql`; el puerto `3306` no se publica.

Mientras la aplicación se publique mediante HTTP, `SESSION_SECURE_COOKIE` debe
permanecer en `false` para que el inicio de sesión funcione. Cuando exista HTTPS,
se debe cambiar a `true`. No se deben utilizar datos ni credenciales reales a
través de una conexión HTTP sin protección.

## Verificación después de cambiar variables

```powershell
php artisan config:clear
php artisan about --only=environment,drivers
php artisan luzparral:health --database --json
```

En el contenedor de producción la configuración se cacheará después de inyectar
las variables privadas. Modificar un archivo de entorno sin reconstruir esa
caché no cambia la configuración activa.

El aprovisionamiento y el endurecimiento previo a publicar se detallan en
[`SEGURIDAD_OPERATIVA.md`](SEGURIDAD_OPERATIVA.md).
La interpretación de las comprobaciones y los logs se detalla en
[`DIAGNOSTICO_OPERATIVO.md`](DIAGNOSTICO_OPERATIVO.md).
