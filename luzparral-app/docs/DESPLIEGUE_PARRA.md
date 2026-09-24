# Despliegue de SIGCEL en Parra

Este procedimiento publica primero una versión candidata en el puerto `2004` y
reserva `2003` para producción. MySQL 8.4 se ejecuta en la misma cuenta como un
contenedor rootless separado, con volumen persistente y sin publicar `3306`.

## Estado comprobado del servidor

La inspección realizada con la cuenta institucional confirmó:

- arquitectura `x86_64` y espacio disponible suficiente en `/home`;
- Podman `5.8.2` en modo `Rootless=true`;
- `Linger=yes`, necesario para persistencia de servicios del usuario;
- puertos `2003` y `2004` libres;
- ausencia de contenedores e imágenes previas en la cuenta.

Todavía deben comprobarse durante el despliegue la apertura externa de los
puertos, el reinicio efectivo de contenedores y la salida del navegador hacia
OpenStreetMap y Windy.

## Arquitectura resultante

- Una instancia `mysql:8.4.11` llamada `luzparral-mysql`.
- Un volumen `luzparral-mysql-data` que no se elimina al recrear el contenedor.
- Una red `luzparral-private` creada con `--internal`, utilizada por MySQL y
  las aplicaciones.
- Una red `luzparral-edge`, utilizada solo por las aplicaciones para publicar
  `2004` y `2003`.
- Bases y usuarios distintos: `sigcel_staging` y `sigcel_production`.
- Aplicaciones separadas, con sus propios archivos privados y volúmenes de
  `storage`.
- MySQL se conecta solo a la red privada. Solo `2004` y `2003` se publican en
  el host; MySQL no utiliza `--publish`.

Las razones y escenarios de calidad se documentan en
[`DECISIONES_ARQUITECTURA_DESPLIEGUE.md`](DECISIONES_ARQUITECTURA_DESPLIEGUE.md).

## 1. Crear el paquete local después del commit

El repositorio debe estar limpio. Desde PowerShell, en `luzparral-app`:

```powershell
.\deploy\EXPORTAR_IMAGEN.ps1
```

El comando construye la aplicación y exporta dos imágenes Linux `amd64`:

- `luzparral-app-COMMIT-linux-amd64.tar`;
- `mysql-8.4.11-linux-amd64.tar`.

Cada TAR posee un `.sha256` y un `.metadata.json`. No utilizar el paquete
antiguo `luzparral-app-f0a05a164686-linux-amd64.tar`.

## 2. Transferir con FileZilla

Conectar por SFTP a `parra.chillan.ubiobio.cl`, puerto `22`, y organizar:

```text
~/luzparral/
|-- artifacts/
|-- backups/
|-- config/
`-- scripts/
```

Transferir a `artifacts/` los dos TAR y sus archivos adyacentes. Transferir a
`scripts/`:

- `load-image.sh`;
- `database.sh`;
- `backup-database.sh`;
- `restore-database.sh`;
- `test-backup-restore.sh`;
- `deploy.sh`;
- `verify.sh`;
- `initialize-staging.sh`.

Transferir temporalmente a `config/` las plantillas `mysql.env.example`,
`parra.env.example`, `parra-production.env.example` y
`staging-seed.env.example`. Las plantillas no contienen claves.

## 3. Preparar permisos y cargar las imágenes

En la sesión SSH:

```bash
mkdir -p ~/luzparral/{artifacts,backups,config,scripts}
chmod 700 ~/luzparral/config ~/luzparral/backups
chmod 755 ~/luzparral/scripts/*.sh

~/luzparral/scripts/load-image.sh ~/luzparral/artifacts/mysql-8.4.11-linux-amd64.tar
~/luzparral/scripts/load-image.sh ~/luzparral/artifacts/luzparral-app-COMMIT-linux-amd64.tar
podman images
```

`load-image.sh` detiene la carga si la suma SHA-256 no coincide.

## 4. Crear las configuraciones privadas

```bash
cp ~/luzparral/config/mysql.env.example ~/luzparral/config/mysql.env
cp ~/luzparral/config/parra.env.example ~/luzparral/config/parra-staging.env
cp ~/luzparral/config/parra-production.env.example ~/luzparral/config/parra-production.env
cp ~/luzparral/config/staging-seed.env.example ~/luzparral/config/staging-seed.env
chmod 600 ~/luzparral/config/*.env
```

Editar los cuatro archivos y reemplazar todos los valores `REEMPLAZAR_*`. Las
claves MySQL deben tener al menos 24 caracteres y usar letras, números, punto,
guion, guion bajo o virgulilla. Se recomienda generarlas con un gestor de
contraseñas. No pegarlas en correos, capturas, Git ni comandos de chat.

Las correspondencias deben ser exactas:

| Archivo de aplicación | Base/usuario/clave en `mysql.env` |
| --- | --- |
| `parra-staging.env` | `MYSQL_STAGING_DATABASE`, `MYSQL_STAGING_USER`, `MYSQL_STAGING_PASSWORD` |
| `parra-production.env` | `MYSQL_PRODUCTION_DATABASE`, `MYSQL_PRODUCTION_USER`, `MYSQL_PRODUCTION_PASSWORD` |

`APP_KEY` debe ser distinta en cada entorno. Puede generarse localmente con
`php artisan key:generate --show`. Mientras la URL use HTTP,
`SESSION_SECURE_COOKIE=false`; con HTTPS debe cambiarse a `true`.

## 5. Iniciar MySQL privado

```bash
~/luzparral/scripts/database.sh start mysql:8.4.11 ~/luzparral/config/mysql.env
~/luzparral/scripts/database.sh status
```

El segundo comando debe mostrar `healthy`, red interna, que MySQL no está unido
a la red de entrada y `Puerto 3306: no publicado`. No se debe agregar
`-p 3306:3306` ni una regla de firewall para MySQL.

## 6. Desplegar staging en 2004

```bash
~/luzparral/scripts/deploy.sh \
  staging \
  luzparral-app:TAG_COMMIT \
  ~/luzparral/config/parra-staging.env \
  ~/luzparral/config/mysql.env
```

El script valida las configuraciones, crea un respaldo, detiene brevemente la
versión anterior si existe, ejecuta `php artisan migrate --force` en un
contenedor efímero y publica la nueva imagen. Si la migración falla, vuelve a
iniciar la aplicación anterior. Si falla `/up`, revierte su imagen; una
reversión de esquema se realiza únicamente mediante un respaldo verificado.

En el primer despliegue, inicializar una sola vez el conjunto de demostración:

```bash
~/luzparral/scripts/initialize-staging.sh \
  ~/luzparral/config/parra-staging.env \
  ~/luzparral/config/staging-seed.env

rm ~/luzparral/config/staging-seed.env
```

El generador se niega a reemplazar información no reconocida. Staging debe
contener exclusivamente datos sintéticos.

## 7. Verificación técnica y funcional

```bash
~/luzparral/scripts/verify.sh staging --database
podman ps
podman port luzparral-mysql
```

El último comando no debe imprimir nada. Desde otro equipo abrir:

`http://parra.chillan.ubiobio.cl:2004`

La aceptación manual debe revisar inicio y cierre de sesión, dashboard,
**filtros por dia, mes, año y rango**, mapa, detalle, búsqueda, pronostico
Windy y los tres informes. La importacion controlada debe permitir previsualizar
el lote sintético, confirmar sin duplicar códigos y **explicar las** causas de
rechazo. Administración debe poder **editar y eliminar** antecedentes con
confirmación y bitácora. El **mapa debe actualizar el area visible**, agrupar
marcadores y conservar las capas agregadas autorizadas.

## 8. Probar respaldo y restauración sin alterar staging

Generar un respaldo manual y tomar la ruta informada:

```bash
~/luzparral/scripts/backup-database.sh \
  staging \
  ~/luzparral/config/mysql.env \
  ~/luzparral/backups

~/luzparral/scripts/test-backup-restore.sh \
  staging \
  ~/luzparral/backups/staging-FECHA.sql
```

El ensayo verifica SHA-256, restaura en una base temporal, cuenta sus tablas y
la elimina. No modifica `sigcel_staging`.

`restore-database.sh` queda reservado para una recuperación real: exige la
aplicación detenida, metadatos coincidentes, checksum válido y destino vacío.
Nunca elimina tablas ni ejecuta `migrate:fresh`.

## 9. Probar persistencia

Sin reiniciar el servidor completo durante horario no autorizado, se puede
validar primero el reinicio de contenedores:

```bash
podman restart luzparral-mysql luzparral-staging
~/luzparral/scripts/verify.sh staging --database
```

La comprobación definitiva tras reinicio del servidor debe coordinarse con la
Universidad y registrarse como evidencia.

## 10. Promover a producción en el puerto `2003`

Solo después de aprobar staging:

```bash
~/luzparral/scripts/deploy.sh \
  production \
  luzparral-app:TAG_COMMIT \
  ~/luzparral/config/parra-production.env \
  ~/luzparral/config/mysql.env

~/luzparral/scripts/verify.sh production --database
```

Producción se crea vacía mediante migraciones. No se ejecuta el generador
sintético ni seeders. Las cuentas autorizadas se aprovisionan con el mecanismo
descrito en [`SEGURIDAD_OPERATIVA.md`](SEGURIDAD_OPERATIVA.md).

## Límites antes de uso empresarial

Hasta disponer de HTTPS, política institucional de copias externas y cuentas
reales autorizadas, la instancia debe tratarse como demostración académica. El
despliegue universitario no equivale al despliegue definitivo dentro de la
empresa.
