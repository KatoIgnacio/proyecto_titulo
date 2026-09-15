# Paquete de despliegue para Parra

Este procedimiento prepara la migracion sin asumir acceso administrativo al
servidor. El puerto `2004` se utiliza para validar una version candidata y el
puerto `2003` para la version estable.

## Limites de este segmento

El paquete queda construido y verificable en local, pero no demuestra todavia:

- conectividad desde Parra hacia MySQL institucional;
- arquitectura y version exacta de Podman disponibles en Parra;
- apertura externa efectiva de los puertos `2003` y `2004`;
- persistencia de contenedores despues de un reinicio del servidor;
- disponibilidad de HTTPS, SMTP, cuota de disco y politica de respaldos.

Estas comprobaciones requieren acceso al servidor y se registraran durante la
migracion. Ningun script de este paquete ejecuta migraciones de base de datos.

## 1. Generar el artefacto en Windows

El repositorio debe estar sin cambios pendientes. Desde PowerShell:

```powershell
.\deploy\EXPORTAR_IMAGEN.ps1
```

El script construye una imagen etiquetada con el commit actual y crea en
`artifacts/` tres archivos:

- la imagen Linux en formato `tar`;
- su suma SHA-256 en `.tar.sha256`;
- metadatos con imagen, commit, plataforma y fecha en `.tar.metadata.json`.

`artifacts/` esta ignorado por Git. No se deben incluir `.env`, credenciales,
respaldos ni datos CIOP en la transferencia.

## 2. Transferir con FileZilla

Conectarse por SFTP a `parra.chillan.ubiobio.cl` en el puerto `22` y transferir:

- los tres archivos generados en `artifacts/`;
- `deploy/parra/load-image.sh`;
- `deploy/parra/deploy.sh`;
- `deploy/parra/verify.sh`;
- `deploy/parra/parra.env.example`.

Una estructura sugerida dentro de la cuenta institucional es:

```text
~/luzparral/
|-- artifacts/
|-- config/
`-- scripts/
```

## 3. Comprobar el servidor antes de desplegar

```bash
uname -m
podman --version
podman info
df -h
```

La arquitectura informada por `uname -m` debe corresponder con la indicada en
el archivo de metadatos. La imagen local validada actualmente es `linux/amd64`,
que normalmente aparece como `x86_64` en Linux.

## 4. Crear la configuracion privada

Crear un archivo independiente para staging y otro para produccion:

```bash
cp parra.env.example ~/luzparral/config/parra-staging.env
cp parra.env.example ~/luzparral/config/parra-production.env
chmod 600 ~/luzparral/config/parra-staging.env
chmod 600 ~/luzparral/config/parra-production.env
```

Reemplazar todos los marcadores `REEMPLAZAR_*`. En staging, `APP_URL` debe usar
el puerto `2004`; en produccion debe usar `2003`. Las credenciales no se agregan
al repositorio ni se incluyen en capturas. `APP_KEY` se puede generar localmente
con `php artisan key:generate --show` y debe copiarse de forma privada.

## 5. Verificar y cargar la imagen

```bash
chmod 755 ~/luzparral/scripts/*.sh
~/luzparral/scripts/load-image.sh ~/luzparral/artifacts/NOMBRE_IMAGEN.tar
podman images luzparral-app
```

`load-image.sh` detiene el proceso si el SHA-256 no coincide. Podman puede cargar
directamente el archivo generado por `docker save`.

## 6. Publicar primero en staging

```bash
~/luzparral/scripts/deploy.sh staging luzparral-app:TAG_COMMIT ~/luzparral/config/parra-staging.env
~/luzparral/scripts/verify.sh staging
~/luzparral/scripts/verify.sh staging --database
```

La ultima comprobacion valida la conexion MySQL y la presencia del esquema
indispensable, pero no modifica la base. Ademas de los comandos, se deben probar
manualmente inicio de sesion, dashboard, filtros, mapa, detalle, busqueda y las
tres variantes de informe. La lectura de respuestas 503 y logs se documenta en
[`DIAGNOSTICO_OPERATIVO.md`](DIAGNOSTICO_OPERATIVO.md).

Después de preparar la base y antes de probar el inicio de sesión, se deben
aprovisionar las cinco cuentas siguiendo
[`SEGURIDAD_OPERATIVA.md`](SEGURIDAD_OPERATIVA.md). No se ejecutan seeders en
Parra y la recuperación web permanece deshabilitada mientras no exista SMTP.

## 7. Promover al puerto estable

Solo despues de aprobar staging:

```bash
~/luzparral/scripts/deploy.sh production luzparral-app:TAG_COMMIT ~/luzparral/config/parra-production.env
~/luzparral/scripts/verify.sh production --database
```

Cada entorno usa un volumen persistente diferente para `storage`. Si la nueva
version no responde en `/up`, el script intenta restaurar automaticamente la
imagen anterior. Una reversion manual tambien se realiza ejecutando `deploy.sh`
con el tag anterior.

## Alternativa: construir en Parra

Si el servidor tiene acceso saliente a los repositorios de Debian, Composer y
npm, se puede transferir el codigo y ejecutar:

```bash
podman build --pull --tag luzparral-app:TAG_COMMIT --file Containerfile .
```

La transferencia de la imagen ya construida es preferible mientras esa salida a
Internet no este confirmada.
