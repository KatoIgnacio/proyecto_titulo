# Ciclo de base de datos

Este documento separa los escenarios de inicializacion, adopcion, actualizacion,
respaldo y restauracion. Los ejemplos usan datos sinteticos y nunca incluyen
credenciales reales.

## Reglas de seguridad

- No ejecutar `migrate:fresh` sobre una base que deba conservarse.
- No ejecutar el generador con `--reset` sin confirmar que el marcador sea
  exclusivamente `luzparral-synthetic-v1`.
- Definir las contrasenas solo como variables de la sesion actual.
- Generar un respaldo antes de adoptar o actualizar una base existente.
- Restaurar siempre en una base vacia; los scripts no eliminan tablas.

## Variables para las herramientas MySQL

```powershell
$env:LUZPARRAL_DB_ALLOWED_DATABASE = 'NOMBRE_BASE_AUTORIZADA'
$env:LUZPARRAL_DB_PASSWORD = '<contraseña>'
```

Al terminar:

```powershell
Remove-Item Env:LUZPARRAL_DB_ALLOWED_DATABASE
Remove-Item Env:LUZPARRAL_DB_PASSWORD
```

## Escenario A: base vacia solo para produccion

Configurar `.env` y ejecutar:

```powershell
php artisan migrate --force
php artisan migrate:status
```

No ejecutar `db:seed`: las cuentas reales se aprovisionaran de forma controlada
en el segmento de seguridad operativa.

La validacion sintetica se ejecuta antes del aprovisionamiento. Despues de
incorporar las cinco cuentas reales, el conjunto deja de ser exclusivamente
sintetico y ese validador ya no corresponde como control de aceptacion.

## Escenario B: base vacia con demostracion sintetica

Primero crear toda la estructura con migraciones, sin seeders:

```powershell
php artisan migrate --force
```

Luego declarar la base autorizada y generar el conjunto:

```powershell
$env:LUZPARRAL_DB_HOST = '127.0.0.1'
$env:LUZPARRAL_DB_PORT = '3306'
$env:LUZPARRAL_DB_DATABASE = 'luzparral'
$env:LUZPARRAL_DB_ALLOWED_DATABASE = 'luzparral'
$env:LUZPARRAL_DB_USERNAME = 'luzparral_app'
$env:LUZPARRAL_DB_PASSWORD = '<contraseña-local>'
$env:LUZPARRAL_DEMO_PASSWORD = '<contraseña-demo-temporal>'
php database/generate_synthetic.php
php artisan luzparral:validate-synthetic --require-runtime
```

## Escenario C: esquema sintetico existente sin historial Laravel

Respaldar primero. Despues, con `.env` apuntando a esa base:

```powershell
php artisan luzparral:validate-synthetic
php artisan luzparral:adopt-existing-schema --confirm-synthetic
php artisan migrate --force
php artisan luzparral:validate-synthetic --require-runtime
```

La adopcion registra como ejecutadas solamente las migraciones que corresponden
a las tablas ya existentes. Las migraciones restantes crean cache, sesiones,
colas y soporte de recuperacion de contrasena.

## Escenario D: base administrada por migraciones

```powershell
php artisan migrate:status
php artisan migrate --pretend
```

Despues del respaldo y de revisar la salida de `--pretend`:

```powershell
php artisan migrate --force
php artisan luzparral:validate-synthetic --require-runtime
```

La segunda orden se utiliza solo cuando la base contiene el conjunto sintetico.

## Crear un respaldo

```powershell
.\database\scripts\backup-mysql.ps1 `
    -Database 'luzparral' `
    -HostName '127.0.0.1' `
    -Port 3306 `
    -Username 'luzparral_app'
```

El respaldo se crea en `backups/`, junto con su SHA-256 y metadatos. La carpeta
esta excluida de Git. La contrasena se pasa al proceso hijo mediante su entorno y
no aparece como argumento de linea de comandos.

## Restaurar y validar

El administrador debe crear previamente una base vacia. Declarar exactamente ese
nombre como base autorizada y ejecutar:

```powershell
.\database\scripts\restore-mysql.ps1 `
    -BackupPath '.\backups\ARCHIVO.sql' `
    -Database 'luzparral_restore_test' `
    -HostName '127.0.0.1' `
    -Port 3306 `
    -Username 'luzparral_app' `
    -ConfirmEmptyTarget
```

El script rechaza archivos cuyo SHA-256 no coincida y destinos que ya contengan
tablas. Para validar el resultado, apuntar temporalmente las variables `DB_*` de
Laravel a la base restaurada y ejecutar:

```powershell
php artisan luzparral:validate-synthetic --require-runtime
php artisan migrate:status
```

Una restauracion valida debe informar todos los controles como `OK` y no debe
tener migraciones pendientes.

## Aplicacion al servidor institucional

En Parra se debe confirmar primero si la universidad ya realiza respaldos de la
base MySQL y si la cuenta asignada posee permisos para `mysqldump`. Ninguna
migracion institucional se ejecutara antes de obtener y comprobar un respaldo.
Las cuentas finales se crean mediante el procedimiento de
[`SEGURIDAD_OPERATIVA.md`](SEGURIDAD_OPERATIVA.md), nunca mediante seeders.
