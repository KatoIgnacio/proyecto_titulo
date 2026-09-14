# Sistema de contingencias Luzparral

Aplicación principal del proyecto de título de Kato Bello Martínez y Carlos
Sepúlveda Navarrete. El sistema utiliza Laravel 12, Inertia, React con
TypeScript y MySQL.

El prototipo histórico permanece en `../CIOP_WEB` y los archivos operacionales
en `../CIOP_DATA`. Ninguno de esos archivos se carga automáticamente en esta
aplicación.

## Preparación local

1. Copiar `.env.example` como `.env`.
2. Completar `DB_PASSWORD` con la clave local, sin guardarla en Git.
3. Ejecutar `composer install` y `npm install`.
4. En una base vacía, ejecutar `php artisan migrate --seed`.
5. Ejecutar `npm run build` y `composer run dev`.

No ejecute `migrate:fresh` sobre una base con información que deba conservarse.
Los datos de demostración deben ser exclusivamente sintéticos o anonimizados.

### Adoptar la base sintética local existente

Si `luzparral` ya contiene el conjunto generado y aún no posee historial de
migraciones, ejecute:

```powershell
php artisan luzparral:adopt-existing-schema --confirm-synthetic
php artisan migrate
```

El primer comando valida el marcador y las columnas esperadas antes de registrar
la línea base. El segundo crea solamente las tablas internas faltantes de Laravel.

## Usuarios sintéticos

El seeder crea cinco usuarios para desarrollo. Todos utilizan temporalmente la
contraseña `LuzparralDemo2026!`; debe cambiarse antes de una validación externa.
El registro público está deshabilitado porque las cuentas serán administradas
por usuarios autorizados.

## Datos sintéticos completos

Los scripts MySQL de `database/synthetic` generan comunas, alimentadores, 5.000
puntos de suministro, 360 contingencias, impactos, historial y errores de
importación controlados. Las credenciales se entregan mediante variables de
entorno y nunca se versionan.

## Configuración por entorno

La separación entre el entorno local y la configuración institucional se
describe en [`docs/CONFIGURACION_ENTORNOS.md`](docs/CONFIGURACION_ENTORNOS.md).
La plantilla `.env.production.example` no contiene secretos y no debe utilizarse
sin reemplazar sus marcadores.

La imagen compatible con Docker y Podman y su ejecución local están descritas en
[`docs/CONTENEDORES.md`](docs/CONTENEDORES.md).
