# Informe de cierre local y premigración a Parra

Este informe distingue lo reproducible en local, lo comprobado mediante acceso
SSH a Parra y lo que solo puede cerrarse después de publicar staging. El
despliegue institucional sigue siendo académico y utiliza datos sintéticos.

## Estado cerrado en local

- La aplicación se construye como imagen Linux `amd64` para Podman rootless.
- El banco integral usa MySQL 8.4.11 privado, aplica migraciones, genera 360
  contingencias sintéticas y recorre módulos y exportaciones.
- Dashboard, tabla, mapa e informes conservan los filtros de día, mes, año y
  rango personalizado.
- Windy es una dependencia degradable; su falla no bloquea MySQL ni los módulos
  internos.
- Roles, sesiones, encabezados, importación controlada, búsqueda, antecedentes
  y bitácora poseen pruebas automatizadas.
- La imagen de aplicación y la imagen MySQL se exportan con SHA-256 y metadatos.
- El despliegue crea un respaldo antes de migrar, mantiene rollback de imagen y
  separa la recuperación de base de datos.
- MySQL no publica `3306`; staging y producción usan bases y usuarios distintos.
- La decisión se vincula a escenarios medibles de disponibilidad, seguridad,
  aislamiento, recuperabilidad, modificabilidad y trazabilidad.

## Infraestructura confirmada en Parra

La sesión SSH confirmó el 22 de septiembre de 2026:

| Control | Resultado |
| --- | --- |
| Cuenta y directorio | Acceso correcto; `/home/katobello2101` |
| Arquitectura | `x86_64`, compatible con los artefactos `linux/amd64` |
| Motor de contenedores | Podman `5.8.2` |
| Modalidad | `Rootless=true` |
| Persistencia de sesión | `Linger=yes` |
| Almacenamiento | 832 GiB disponibles al momento de la revisión |
| Puertos asignados | `2003` y `2004` libres |
| Estado inicial | Sin contenedores ni imágenes previas |

La conectividad SSH funciona desde la red institucional. La clave privada
permanece únicamente en el equipo autorizado.

## Verificación local del Segmento 20

La revisión del 24 de septiembre de 2026 registró:

- `composer validate --strict`, Pint y la construcción Vite aprobados;
- **139 pruebas aprobadas y 1.482 aserciones**;
- sintaxis PHP, PowerShell y Bash validada;
- integración completa aprobada con MySQL 8.4.11, 20 tablas, 360
  contingencias, autenticación, módulos HTTP y exportaciones CSV/PDF;
- MySQL conectado únicamente a la red interna, sin puertos publicados;
- aplicación conectada a la red interna de datos y a una red de entrada, con
  `8080` accesible solo como `127.0.0.1:8080` en el ensayo local.

El primer ensayo conectó ambos contenedores únicamente a la red interna: las
comprobaciones dentro del contenedor eran saludables, pero el puerto publicado
no era accesible desde el host. La topología fue corregida a dos redes y el
ensayo completo se repitió satisfactoriamente. Este hallazgo evita trasladar el
problema al servidor institucional.

## Decisión sobre la base de datos

La base MySQL institucional inicialmente considerada no es utilizable. Se
preparó MySQL Community 8.4 dentro de Parra con estas restricciones:

- una instancia para reducir consumo de memoria;
- esquemas y usuarios independientes para staging y producción;
- red interna `luzparral-private` para aplicación y MySQL;
- red de entrada `luzparral-edge` solo para las aplicaciones;
- volumen `luzparral-mysql-data`;
- MySQL no se une a la red de entrada ni publica el puerto `3306`;
- respaldos por entorno con checksum y ensayo de restauración temporal.

Esto no expone MySQL a Internet. Solo se publican los puertos de la aplicación.

## Paquete que debe generarse

El paquete `luzparral-app-f0a05a164686-linux-amd64.tar` y sus archivos
adyacentes quedan **obsoletos**. El candidato se genera únicamente **después del
commit limpio de este segmento** mediante:

```powershell
.\deploy\EXPORTAR_IMAGEN.ps1
```

Se transferirán:

- `artifacts/luzparral-app-COMMIT-linux-amd64.tar` y sus dos archivos de control;
- `artifacts/mysql-8.4.11-linux-amd64.tar` y sus dos archivos de control;
- todos los `.sh` de `deploy/parra/`;
- `mysql.env.example`, `parra.env.example`,
  `parra-production.env.example` y `staging-seed.env.example`.

No se transfieren `.env` privados, contraseñas, respaldos locales, datos CIOP
ni archivos de usuarios reales.

## Pendiente exclusivamente en Parra

1. Cargar los dos artefactos y comprobar sus SHA-256.
2. Crear los archivos privados con modo `600`, sin capturas ni envío por correo.
3. Iniciar MySQL y demostrar que `podman port luzparral-mysql` no entrega salida.
4. Publicar staging en el puerto `2004` y comprobar su acceso desde otro equipo.
5. Inicializar y validar exclusivamente el conjunto sintético.
6. Crear un respaldo y aprobar el ensayo no destructivo de restauración.
7. Validar la persistencia tras reinicio de contenedores y, cuando se autorice,
   tras reinicio del servidor.
8. Verificar acceso del navegador a OpenStreetMap y
   `https://embed.windy.com`; sus fallas no deben bloquear el sistema.
9. Confirmar HTTPS o mantener la restricción de no usar datos ni claves reales
   sobre HTTP.
10. Acordar retención de respaldos, monitoreo, incidentes y responsables.

## Aceptación obligatoria en staging — puerto 2004

- [ ] Ambos SHA-256 coinciden antes de cargar las imágenes.
- [ ] `database.sh status` informa MySQL saludable, red interna y 3306 privado.
- [ ] `deploy.sh staging` genera respaldo, migra y termina correctamente.
- [ ] `verify.sh staging --database` confirma aplicación, conexión y esquema.
- [ ] `test-backup-restore.sh` restaura el respaldo en una base temporal.
- [ ] Inicio y cierre de sesión funcionan con cada rol autorizado.
- [ ] Las restricciones de administración e informes se cumplen en backend.
- [ ] Dashboard y tabla responden a día, mes, año y rango personalizado.
- [ ] El mapa agrupa marcadores, actualiza el área visible y, con Internet,
      carga OpenStreetMap.
- [ ] Las capas críticas y electrodependientes no revelan identificadores
      individuales ni aparecen para Consulta.
- [ ] Detalle, búsqueda y trazabilidad muestran datos consistentes.
- [ ] La importación controlada respeta roles, evita duplicados y muestra el
      motivo de cada rechazo.
- [ ] Solo Administración puede editar o eliminar antecedentes y la bitácora
      conserva cada operación.
- [ ] Los informes completo, resumen gráfico y evolución conservan filtros.
- [ ] Windy carga cuando existe salida y su falla no bloquea el resto.
- [ ] No aparecen datos reales no autorizados ni credenciales en interfaz/logs.
- [ ] Se repite la medición gradual con 5, 10 y 30 sesiones y se revisan P95,
      errores 5xx, CPU y memoria.
- [ ] Se ejecuta el protocolo de usuarios o se documenta su fecha posterior sin
      declarar OE4/RNF05 antes de medirlo.
- [ ] Se prueba una reversión al tag anterior y se identifica el respaldo previo.
- [ ] Se verifica la persistencia después de reiniciar los contenedores.

## Promoción y cierre — puerto 2003

Producción solo se publica tras aprobar staging. Utiliza
`parra-production.env`, `sigcel_production`, su usuario exclusivo y un volumen
de aplicación separado. No recibe seeders ni datos sintéticos automáticamente.

El cierre debe registrar tag, commit, SHA-256, fecha, responsable, resultado de
aceptación, respaldo previo y plan de reversión. La aprobación académica no
autoriza por sí sola el posterior despliegue empresarial con datos reales.
