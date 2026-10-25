# Seguridad operativa

La aplicación utiliza un máximo de diez cuentas activas y parte con cinco:
un administrador, un supervisor, dos operadores y un usuario de consulta. Las
cuentas se crean fuera del código y las contraseñas nunca se entregan como
argumentos, se imprimen ni se guardan en el repositorio.

## Datos sintéticos locales

El generador y el seeder ya no contienen una contraseña conocida. Antes de
usarlos se debe declarar una clave temporal de al menos doce caracteres:

```powershell
$env:LUZPARRAL_DEMO_PASSWORD = '<clave-temporal-distinta>'
php database/synthetic/generate_synthetic.php
Remove-Item Env:LUZPARRAL_DEMO_PASSWORD
```

El generador no muestra la clave en su salida. El seeder se niega a ejecutarse
cuando `APP_ENV=production`.

## Aprovisionar las cinco cuentas base

Antes de este paso debe haberse ejecutado
`php artisan luzparral:validate-synthetic --require-runtime`. Esa validación
comprueba el conjunto exclusivamente sintético y deja de corresponder una vez
incorporadas las cuentas reales de acceso.

1. Copiar `docs/examples/users.production.example.json` a una ubicación privada
   fuera del repositorio.
2. Reemplazar nombres y correos. En producción se deben sustituir también los
   dominios reservados del ejemplo.
3. Declarar las cinco variables indicadas en `password_env`, cada una con una
   contraseña distinta de al menos doce caracteres, mayúsculas, minúsculas,
   números y símbolos.
4. Ejecutar el aprovisionamiento con confirmación explícita:

```powershell
php artisan luzparral:provision-users `
    --file='C:\ruta-privada\luzparral-users.json' `
    --confirm
```

El comando valida la distribución de roles, impide superar diez usuarios
activos, desactiva las cuentas `example.invalid` y revoca sus sesiones. Para
rotar las cinco cuentas ya existentes se agrega `--update-existing`.

En Parra, el JSON sin contraseñas puede copiarse temporalmente al contenedor.
Las claves se leen de forma oculta y se pasan por nombre de variable:

```bash
read -rsp 'Clave administrador: ' LUZPARRAL_USER_ADMIN_PASSWORD; echo
read -rsp 'Clave supervisor: ' LUZPARRAL_USER_SUPERVISOR_PASSWORD; echo
read -rsp 'Clave operador 1: ' LUZPARRAL_USER_OPERATOR1_PASSWORD; echo
read -rsp 'Clave operador 2: ' LUZPARRAL_USER_OPERATOR2_PASSWORD; echo
read -rsp 'Clave consulta: ' LUZPARRAL_USER_VIEWER_PASSWORD; echo
export LUZPARRAL_USER_ADMIN_PASSWORD LUZPARRAL_USER_SUPERVISOR_PASSWORD
export LUZPARRAL_USER_OPERATOR1_PASSWORD LUZPARRAL_USER_OPERATOR2_PASSWORD
export LUZPARRAL_USER_VIEWER_PASSWORD

podman cp /ruta-privada/luzparral-users.json luzparral-staging:/tmp/luzparral-users.json
podman exec --env LUZPARRAL_USER_ADMIN_PASSWORD \
    --env LUZPARRAL_USER_SUPERVISOR_PASSWORD \
    --env LUZPARRAL_USER_OPERATOR1_PASSWORD \
    --env LUZPARRAL_USER_OPERATOR2_PASSWORD \
    --env LUZPARRAL_USER_VIEWER_PASSWORD \
    luzparral-staging php artisan luzparral:provision-users \
    --file=/tmp/luzparral-users.json --confirm
podman exec luzparral-staging rm -f /tmp/luzparral-users.json
unset LUZPARRAL_USER_ADMIN_PASSWORD LUZPARRAL_USER_SUPERVISOR_PASSWORD
unset LUZPARRAL_USER_OPERATOR1_PASSWORD LUZPARRAL_USER_OPERATOR2_PASSWORD
unset LUZPARRAL_USER_VIEWER_PASSWORD
```

## Autorregistro de cuentas de Consulta

Una persona evaluadora puede abrir `/register` desde la pantalla de acceso y
crear su propia cuenta temporal de Consulta. El formulario exige nombre,
correo único y una contraseña de al menos 12 caracteres con mayúsculas,
minúsculas, números y símbolos. La cuenta vence automáticamente después de
siete días, se somete a limitación de frecuencia y siempre recibe el rol
Consulta; el cliente no puede solicitar ni alterar privilegios superiores.

No existe un flujo administrativo paralelo para generar credenciales. Cada
persona evaluadora define su propio correo y contraseña en el formulario
público; esto evita entregar claves por canales externos y mantiene un único
punto de entrada verificable.

El estado efectivo exige simultáneamente `active=true` y un `expires_at`
futuro. Al cumplirse los siete días, el middleware cierra el acceso. Las
cuentas de autorregistro forman parte del máximo institucional de diez cuentas
activas.

Existe un único rol Consulta. Una cuenta de Consulta puede ser permanente o
temporal; la diferencia es exclusivamente su fecha de vencimiento, no sus
permisos. Puede consultar el panel general, el buscador, el detalle de
contingencias y los informes. No puede acceder al mapa, al pronóstico
meteorológico, a importaciones, a identificadores de cliente o suministro ni a
rutas que modifiquen información. Estas restricciones se aplican en backend y
no dependen únicamente de ocultar opciones en la interfaz.

## Recuperación sin SMTP

`PASSWORD_RESET_ENABLED=false` oculta y bloquea la recuperación web. No se
deben escribir enlaces de recuperación en los logs de producción. Mientras no
exista SMTP, un administrador del servidor rota una contraseña mediante una
variable temporal y el comando revoca las sesiones anteriores:

```bash
read -rsp 'Nueva clave: ' LUZPARRAL_RESET_PASSWORD; echo
export LUZPARRAL_RESET_PASSWORD
podman exec --env LUZPARRAL_RESET_PASSWORD luzparral-staging \
    php artisan luzparral:reset-user-password usuario@dominio.cl --confirm
unset LUZPARRAL_RESET_PASSWORD
```

La recuperación web solo se habilita después de configurar y probar SMTP,
cambiar `MAIL_MAILER=smtp` y establecer `PASSWORD_RESET_ENABLED=true`.

## Sesiones y cuentas

- El registro público crea exclusivamente cuentas temporales de Consulta y no
  permite elegir roles. No existe eliminación de la propia cuenta.
- La sesión de producción dura 60 minutos y expira al cerrar el navegador.
- La opción de recordar la sesión está deshabilitada.
- Las sesiones se almacenan cifradas en MySQL; las cookies son HTTP-only y
  `SameSite=lax`.
- `SESSION_SECURE_COOKIE` permanece en `false` únicamente mientras Parra use
  HTTP. Debe cambiar a `true` al disponer de HTTPS.

## Encabezados y archivos

En producción se habilitan CSP, `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy` y `Cross-Origin-Opener-Policy`. La CSP
permite exclusivamente los recursos locales, las teselas HTTPS de
OpenStreetMap requeridas por el mapa y el marco oficial
`https://embed.windy.com` utilizado por el pronóstico meteorológico.

El contenedor asigna `storage` y `bootstrap/cache` a `www-data` con permisos
para propietario y grupo, sin acceso para otros usuarios. El archivo privado de
variables de Parra debe conservar modo `600`.

## Versión segura del runtime PHP

La imagen de SIGCEL fija `php:8.3.36-apache-bookworm`. PHP 8.3.35, que aún se
encuentra en el staging institucional hasta publicar la próxima imagen, está
afectado por CVE-2026-109618 y CVE-2026-109619. Debido a que SIGCEL utiliza
Apache con `mod_php`, no se debe promover una imagen construida con una versión
anterior a PHP 8.3.36.

Como defensa adicional, `deploy/php-production.ini` mantiene
`session.upload_progress.enabled = 0`. Esta configuración no sustituye la
actualización del runtime. Antes de transferir una imagen se debe verificar la
versión que contiene:

```powershell
docker run --rm --entrypoint php luzparral-app:ETIQUETA `
    -r "echo PHP_VERSION, PHP_EOL;"
```

Después del despliegue se repite dentro del contenedor institucional:

```bash
podman exec luzparral-staging php -r 'echo PHP_VERSION, PHP_EOL;'
podman exec luzparral-staging php -r \
    'echo ini_get("session.upload_progress.enabled"), PHP_EOL;'
```

La salida exigida es PHP `8.3.36` o una revisión 8.3 posterior que incorpore
las correcciones, y `0` para el progreso de carga de sesión.

## Protección frente a inyección SQL

Los filtros de dashboard, buscador, mapa e informes validan tipo, longitud y
valores permitidos antes de construir consultas. Los valores libres se entregan
a Eloquent o Query Builder como parámetros enlazados; no se concatenan en SQL.
Las expresiones Raw son constantes del sistema. En el mapa, la precisión
interpolada se obtiene de un zoom entero validado y se transforma a uno de
cuatro valores internos.

SqlInjectionProtectionTest envía cargas de unión, tautología y eliminación como
texto libre, comprueba que no devuelvan registros y verifica que la tabla
permanezca intacta. También confirma que fragmentos introducidos en estado,
categoría, tipo de informe y zoom sean rechazados por validación. Todo nuevo
filtro debe conservar estas reglas: nunca interpolar texto de la solicitud en
métodos Raw.

## Control previo a publicar

Ejecutar desde la raíz del repositorio:

```powershell
git status --short
git grep -n -I -E 'BEGIN .*PRIVATE KEY|AKIA[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9]{20,}|AIza[0-9A-Za-z_-]{30,}' -- . ':(exclude)docs/SEGURIDAD_OPERATIVA.md'
git ls-files | Select-String -Pattern '(^|/)(\.env$|.*\.pem$|.*\.pfx$|.*\.p12$|.*\.tar$)'
```

Una coincidencia debe revisarse antes de exportar. Las plantillas `.env` sin
valores privados son las únicas excepciones previstas.
