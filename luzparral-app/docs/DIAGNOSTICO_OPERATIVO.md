# Diagnóstico operativo

Esta guía permite distinguir una aplicación viva de una aplicación que además
puede consultar la base de datos esperada. Ninguna de estas comprobaciones
ejecuta migraciones ni modifica registros.

## Salud de la aplicación

La ruta `/up` comprueba que Laravel y el servidor web responden. No depende de
MySQL, por lo que sirve como prueba de vida del contenedor:

```text
GET /up
```

La misma comprobación puede ejecutarse dentro del contenedor:

```bash
php artisan luzparral:health --json
```

## Salud de la base de datos

Para comprobar conexión y presencia de las tablas indispensables:

```bash
php artisan luzparral:health --database --json
```

Una salida correcta informa `application`, `database` y `schema` con valor
`ok`. El comando retorna código `0` al aprobar y un código distinto de cero al
fallar. El error público no incluye host, usuario, contraseña ni mensaje del
motor; entrega un identificador de diagnóstico para buscar el detalle seguro en
los logs.

En Parra se ejecuta mediante el verificador del paquete de despliegue:

```bash
~/luzparral/scripts/verify.sh staging --database
~/luzparral/scripts/verify.sh production --database
```

## Logs con Podman

Los entornos contenedorizados deben usar `LOG_CHANNEL=stderr_json`. Cada evento
queda como una línea JSON consultable sin entrar al contenedor:

```bash
podman logs --since 15m luzparral-staging
podman logs --since 15m luzparral-production
```

Para seguir un identificador entregado por una respuesta HTTP 503:

```bash
podman logs --since 30m luzparral-staging | grep IDENTIFICADOR
```

Los eventos relevantes son:

- `http.database_unavailable`: una petición web no pudo conectarse a MySQL.
- `health.database_failed`: falló la comprobación explícita de base o esquema.

Ante una indisponibilidad de conexión, la aplicación devuelve HTTP `503`, el
encabezado `Retry-After: 30` y una explicación comprensible. No expone el error
SQL ni las credenciales. El orden de revisión recomendado es: estado del
contenedor, variables `DB_*`, conectividad al servidor MySQL, existencia del
esquema y, finalmente, el identificador de diagnóstico en los logs.
