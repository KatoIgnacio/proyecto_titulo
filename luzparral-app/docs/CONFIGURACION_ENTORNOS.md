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

`.env.production.example` documenta las variables necesarias para Parra. En el
servidor se debe crear un archivo privado `.env.production` a partir de esta
plantilla y reemplazar los marcadores de MySQL. Ese archivo ya está ignorado por
Git y no debe incorporarse a una imagen ni transferirse a terceros.

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
- `LOG_CHANNEL=stderr` para consultar los logs mediante Podman.
- `SESSION_ENCRYPT=true`, cookie HTTP-only y `SameSite=lax`.
- `QUEUE_CONNECTION=sync`, porque la versión actual no requiere un proceso
  worker separado.
- Credenciales institucionales de MySQL almacenadas solo como secreto.

Mientras la aplicación se publique mediante HTTP, `SESSION_SECURE_COOKIE` debe
permanecer en `false` para que el inicio de sesión funcione. Cuando exista HTTPS,
se debe cambiar a `true`. No se deben utilizar datos ni credenciales reales a
través de una conexión HTTP sin protección.

## Verificación después de cambiar variables

```powershell
php artisan config:clear
php artisan about --only=environment,drivers
```

En el contenedor de producción la configuración se cacheará después de inyectar
las variables privadas. Modificar un archivo de entorno sin reconstruir esa
caché no cambia la configuración activa.
