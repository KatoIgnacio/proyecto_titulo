# SIGCEL Luzparral

Sistema de Información para la Gestión de Contingencias Eléctricas de Luzparral.

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
4. En una base vacía, ejecutar `php artisan migrate` sin seeders.
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

El seeder crea cinco usuarios para desarrollo solamente cuando se proporciona
`LUZPARRAL_DEMO_PASSWORD`; no contiene ni imprime una contraseña conocida y se
niega a ejecutarse en producción. El registro público está deshabilitado.

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
Esa guía incluye el ensayo integral aislado con Docker, MySQL 8.4.9 y el
conjunto sintético completo.

Los escenarios de migración, generación sintética, respaldo, restauración y
validación están descritos en [`docs/CICLO_BASE_DATOS.md`](docs/CICLO_BASE_DATOS.md).

El aprovisionamiento de las cinco cuentas, la recuperación sin SMTP y las
protecciones de sesión están documentados en
[`docs/SEGURIDAD_OPERATIVA.md`](docs/SEGURIDAD_OPERATIVA.md).

La exportación verificable de la imagen y el procedimiento para los puertos
institucionales `2003` y `2004` están documentados en
[`docs/DESPLIEGUE_PARRA.md`](docs/DESPLIEGUE_PARRA.md).
El cierre local y los controles que solo pueden resolverse dentro del servidor
se separan en
[`docs/INFORME_PREMIGRACION_PARRA.md`](docs/INFORME_PREMIGRACION_PARRA.md).

La comprobación de salud, los errores controlados de base de datos y la lectura
de logs están documentados en
[`docs/DIAGNOSTICO_OPERATIVO.md`](docs/DIAGNOSTICO_OPERATIVO.md). Las
dependencias de ejecución, construcción y datos se detallan en
[`docs/DEPENDENCIAS_EXTERNAS.md`](docs/DEPENDENCIAS_EXTERNAS.md).

La validación de calidad, los períodos calendario por día, mes, año o rango,
la recuperación del pronóstico Windy y la prueba de 5/10 sesiones concurrentes
están documentados en
[`docs/CALIDAD_RENDIMIENTO.md`](docs/CALIDAD_RENDIMIENTO.md).

La secuencia de estados, el registro atómico de cambios y las autorizaciones de
la bitácora se describen en
[`docs/TRAZABILIDAD_CONTINGENCIAS.md`](docs/TRAZABILIDAD_CONTINGENCIAS.md).

El registro de avances en terreno, la carga privada de evidencias y sus
permisos se describen en
[`docs/ANTECEDENTES_TERRENO.md`](docs/ANTECEDENTES_TERRENO.md).
