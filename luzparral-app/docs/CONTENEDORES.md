# Contenedores Docker y Podman

La misma imagen OCI se utiliza en ambos entornos:

- Docker Desktop para construcción y pruebas locales.
- Podman rootless para ejecución en el servidor institucional Parra.
- Puerto interno único `8080`.

MySQL no se incluye en la imagen. La aplicación se conecta a una base externa
mediante las variables `DB_*`.

## Construcción local con Docker

```powershell
docker build --pull --tag luzparral-app:local --file Containerfile .
```

La construcción instala las dependencias PHP sin paquetes de desarrollo,
compila React y genera una imagen final con Apache y PHP 8.3. El contexto excluye
credenciales, dependencias locales, compilados anteriores, respaldos y datos de
CIOP.

## Entorno local equivalente a producción

Crear el archivo privado de configuración y completar `APP_KEY` y
`DB_PASSWORD`:

```powershell
Copy-Item .env.container.local.example .env.container.local
php artisan key:generate --show
docker compose --file compose.local.yaml up --build --detach
docker compose --file compose.local.yaml ps
```

Para validar solamente la sintaxis del archivo sin crear el entorno privado:

```powershell
$env:LUZPARRAL_ENV_FILE='.env.container.local.example'
docker compose --file compose.local.yaml config --quiet
Remove-Item Env:LUZPARRAL_ENV_FILE
```

El contenedor se publica únicamente en `127.0.0.1:8080`. En Docker Desktop,
`host.docker.internal` permite acceder al MySQL instalado en Windows. El usuario
de MySQL deberá aceptar conexiones provenientes de Docker.

La ruta de salud es:

```text
http://127.0.0.1:8080/up
```

## Comprobaciones del contenedor

```powershell
docker compose --file compose.local.yaml exec app php artisan luzparral:health --json
docker compose --file compose.local.yaml exec app php artisan luzparral:health --database --json
docker compose --file compose.local.yaml logs --tail 100 app
```

La segunda comprobación valida la conexión y las tablas indispensables sin
modificar la base. Los logs del contenedor se emiten como JSON; la guía de
diagnóstico se encuentra en
[`DIAGNOSTICO_OPERATIVO.md`](DIAGNOSTICO_OPERATIVO.md).

## Ensayo integral local aislado

`compose.integration.yaml` levanta temporalmente la imagen de la aplicación y
un MySQL 8.4.11 exclusivo para pruebas. MySQL continúa separado de la imagen de
Luzparral: este servicio adicional es solo un banco de integración local y no
forma parte del despliegue en Parra.

La prueba genera claves aleatorias en memoria, crea el esquema, carga el
conjunto `luzparral-synthetic-v1` y valida por HTTP el acceso, dashboard, mapa,
búsqueda, detalle, informes y las exportaciones CSV/PDF:

```powershell
powershell -ExecutionPolicy Bypass -File deploy/VALIDAR_INTEGRACION_LOCAL.ps1
```

El proceso utiliza `127.0.0.1:8080`, elimina al terminar los contenedores y el
volumen sintético que creó, y no modifica la MySQL instalada en Windows. Si una
comprobación falla, conserva el banco de prueba para consultar los logs. Para
mantenerlo también después de una ejecución correcta:

```powershell
powershell -ExecutionPolicy Bypass -File deploy/VALIDAR_INTEGRACION_LOCAL.ps1 -KeepContainers
```

Antes de repetir el ensayo se elimina exclusivamente el proyecto Compose
`luzparral-integration`, incluido su volumen descartable.

Las migraciones nunca se ejecutan automáticamente al iniciar el contenedor. Esto
evita modificar accidentalmente una base institucional. El procedimiento de
migración se definirá en el segmento de base de datos.

## Compatibilidad con Podman

La imagen no utiliza Docker Compose ni servicios propios de Docker para operar.
En un equipo con Podman puede construirse con:

```bash
podman build --pull --tag luzparral-app:local --file Containerfile .
podman run --rm --env-file .env.production -p 2004:8080 luzparral-app:local
```

El comando definitivo del servidor, la persistencia, el puerto 2003 y el
procedimiento de reversión se documentarán en el paquete de migración.
