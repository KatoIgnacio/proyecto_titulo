# Informe de cierre local y premigración a Parra

Este documento delimita qué está terminado y reproducible en el equipo local y
qué debe comprobarse todavía en la infraestructura de la Universidad del
Bío-Bío. Una compilación local correcta no equivale por sí sola a un despliegue
institucional aprobado.

## Estado cerrado en local

- La aplicación se construye como una imagen Linux `amd64` compatible con
  Docker y destinada a ejecución rootless con Podman.
- El ensayo integral crea una base MySQL 8.4.9 aislada, aplica migraciones,
  genera el conjunto sintético, valida el esquema y recorre los módulos
  autenticados sin utilizar información operacional real.
- Dashboard, mapa, detalle, búsqueda e informes comparten los filtros de
  período. Se verificaron las selecciones por día, mes, año y rango, incluida
  su conservación en las exportaciones CSV y PDF.
- El pronóstico Windy está aislado como dependencia externa. Una falla de Windy
  no impide consultar los módulos respaldados por MySQL.
- Los roles, cuentas inactivas, recuperación sin SMTP, sesiones, encabezados de
  seguridad y límite de usuarios poseen pruebas automatizadas.
- La importación controlada permite validar archivos sintéticos antes de
  escribir, registra aceptaciones y rechazos, evita duplicados y revierte el
  lote completo ante un fallo inesperado.
- La imagen se exporta junto con su suma SHA-256 y metadatos del commit mediante
  `deploy/EXPORTAR_IMAGEN.ps1`. El script rechaza por defecto un repositorio con
  cambios pendientes.
- Los scripts de Parra separan staging en el puerto `2004` y producción en el
  puerto `2003`, comprueban la salud y conservan una ruta de reversión.

## Archivos que se transferirán

Después del commit definitivo se debe ejecutar:

```powershell
.\deploy\EXPORTAR_IMAGEN.ps1
```

Se transfieren por SFTP, sin renombrarlos:

- `artifacts/luzparral-app-COMMIT-linux-amd64.tar`;
- el archivo `.tar.sha256` adyacente;
- el archivo `.tar.metadata.json` adyacente;
- `deploy/parra/load-image.sh`;
- `deploy/parra/deploy.sh`;
- `deploy/parra/verify.sh`;
- `deploy/parra/parra.env.example`.

No se transfieren `.env`, contraseñas, respaldos locales, datos CIOP ni archivos
de usuarios reales.

## Pendiente exclusivamente en Parra

Estos puntos no pueden certificarse desde el equipo local:

1. **Capacidad del servidor:** confirmar arquitectura con `uname -m`, versión y
   modo rootless de Podman, espacio y cuota de disco.
2. **Persistencia tras reinicio:** validar que el contenedor vuelva a iniciar
   después de reiniciar el servidor. Si la política `unless-stopped` no basta en
   la cuenta rootless, la Universidad debe habilitar `systemd --user` y
   persistencia de sesión, o indicar su mecanismo oficial.
3. **Puertos institucionales:** comprobar desde otro equipo que `2004` y `2003`
   son accesibles según la política de firewall, y que no existe otro servicio
   ocupándolos.
4. **MySQL institucional:** confirmar host utilizable desde Parra, permisos de
   conexión y migración, nombre de la base, codificación `utf8mb4` y latencia.
   Las credenciales se guardan únicamente en los archivos privados con modo
   `600`.
5. **Respaldo y restauración:** confirmar si la Universidad respalda MySQL, su
   retención y el responsable de restaurar. Antes de cualquier migración debe
   existir un respaldo verificable o una base vacía expresamente autorizada.
6. **HTTPS:** confirmar si habrá proxy inverso o certificado institucional. Con
   HTTP, `SESSION_SECURE_COOKIE=false` permite operar, pero no se deben ingresar
   datos ni credenciales reales por una red no protegida. Con HTTPS debe quedar
   en `true`.
7. **Salida a Internet:** verificar acceso HTTPS a las teselas de OpenStreetMap
   y a `https://embed.windy.com`. Si están bloqueados, solo se degradan el fondo
   cartográfico o el pronóstico, respectivamente.
8. **Cuentas finales:** recibir nombres, correos y roles autorizados; crear las
   cinco cuentas mediante el comando documentado y retirar las cuentas de
   demostración. Las claves se entregan por un canal privado.
9. **Operación:** acordar responsable, retención de logs, monitoreo, ventana de
   mantenimiento, procedimiento de incidentes y disponibilidad futura de SMTP.

## Aceptación obligatoria en staging — puerto 2004

No promover a producción hasta marcar todos los controles:

- [ ] La suma SHA-256 de la imagen coincide antes de cargarla.
- [ ] `deploy.sh staging` termina correctamente y `/up` responde.
- [ ] `verify.sh staging --database` confirma aplicación, conexión y esquema.
- [ ] El inicio y cierre de sesión funcionan con cada rol autorizado.
- [ ] Las restricciones de administración e informes se cumplen en backend.
- [ ] Dashboard y tabla responden a día, mes, año y rango personalizado.
- [ ] El mapa muestra marcadores; si hay Internet, carga OpenStreetMap.
- [ ] Detalle, búsqueda y trazabilidad muestran datos consistentes.
- [ ] Administración y Supervisión pueden previsualizar e importar la plantilla
      sintética; Operación y Consulta reciben acceso denegado.
- [ ] Un código repetido queda rechazado y no crea otra contingencia.
- [ ] Los informes completo, resumen gráfico y evolución se generan.
- [ ] CSV y PDF conservan los filtros aplicados.
- [ ] Windy carga cuando existe salida a Internet y su falla no bloquea el resto.
- [ ] No aparecen datos reales no autorizados ni credenciales en interfaz o logs.
- [ ] Se registra una medición breve con cinco sesiones y se revisan errores 5xx.
- [ ] Se prueba una reversión al tag anterior o se documenta su simulación.

## Promoción y cierre — puerto 2003

Con staging aprobado, se crea un archivo de producción independiente, se cambia
`APP_URL` al puerto `2003` y se ejecutan `deploy.sh production` y
`verify.sh production --database`. Finalmente se registra el tag, commit,
SHA-256, fecha, responsable, resultado de la aceptación y plan de reversión.

El segmento local queda finalizado cuando las pruebas, construcción y
exportación se ejecutan sobre un commit limpio. La migración institucional queda
finalizada únicamente cuando todos los controles anteriores poseen evidencia en
Parra.
