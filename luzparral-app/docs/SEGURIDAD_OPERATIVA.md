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

- No existe registro público ni eliminación de la propia cuenta.
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

## Control previo a publicar

Ejecutar desde la raíz del repositorio:

```powershell
git status --short
git grep -n -I -E 'BEGIN .*PRIVATE KEY|AKIA[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9]{20,}|AIza[0-9A-Za-z_-]{30,}' -- . ':(exclude)docs/SEGURIDAD_OPERATIVA.md'
git ls-files | Select-String -Pattern '(^|/)(\.env$|.*\.pem$|.*\.pfx$|.*\.p12$|.*\.tar$)'
```

Una coincidencia debe revisarse antes de exportar. Las plantillas `.env` sin
valores privados son las únicas excepciones previstas.
