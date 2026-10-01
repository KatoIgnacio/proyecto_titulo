# Operación de SIGCEL en Parra

Esta guía concentra los controles posteriores al despliegue de SIGCEL. Está
orientada al staging académico en `8004` y a la futura producción en `8003`.
No sustituye las políticas institucionales ni autoriza el uso de datos reales.

## Alcance y responsabilidades

Los scripts del repositorio permiten comprobar aplicación, base de datos,
esquema, redes, puertos, persistencia lógica y respaldos sin mostrar secretos.
La Universidad debe resolver o confirmar por separado:

- el alcance de red desde el que se permite acceder a `8004` y `8003`;
- la publicación mediante HTTPS y su certificado;
- una copia de respaldos fuera del mismo servidor;
- la ventana autorizada para reiniciar el servidor completo;
- responsables, retención definitiva y canal formal de incidentes.

Hasta cerrar esos puntos, staging continúa siendo una demostración académica
con datos exclusivamente sintéticos.

## Auditoría operativa de solo lectura

Ejecutar después de cada despliegue y antes de cada demostración:

```bash
~/luzparral/scripts/operational-check.sh staging
```

Para producción, una vez autorizada y desplegada:

```bash
~/luzparral/scripts/operational-check.sh production
```

El control ejecuta `verify.sh --database` y además exige:

- Podman rootless y `Linger=yes`;
- política `restart=unless-stopped` y `AutoRemove=false`;
- puerto asignado `8004` o `8003`, sin listeners en `2004/2003`;
- volúmenes persistentes de MySQL y de la aplicación;
- configuraciones y respaldos privados con modo `600`;
- respaldo con SQL, SHA-256 y metadatos concordantes, advirtiendo si supera
  ocho días;
- lectura del uso de disco, con advertencia desde 85 %.

El script no crea respaldos, no reinicia contenedores y no modifica datos.

## Prueba controlada de persistencia

Esta prueba produce una interrupción breve. Debe ejecutarse fuera de una
demostración y requiere una confirmación escrita en el comando:

```bash
~/luzparral/scripts/test-persistence.sh \
  staging \
  --confirm-restart \
  ~/luzparral/config/mysql.env \
  ~/luzparral/backups
```

El procedimiento crea primero un respaldo, reinicia MySQL, espera que quede
saludable, reinicia la aplicación y termina con la auditoría operativa. No
elimina contenedores, volúmenes ni bases.

Esta prueba cubre el reinicio de contenedores. La persistencia tras reiniciar el
servidor completo solo puede declararse aprobada después de coordinar una
ventana con la Universidad y volver a ejecutar `operational-check.sh`.

## Rutina recomendada

| Momento | Control | Evidencia |
| --- | --- | --- |
| Antes de una demostración | `operational-check.sh staging` | Salida completa y fecha |
| Después de desplegar | `verify.sh` y auditoría operativa | Tag, commit, SHA-256 y resultado |
| Semanal mientras staging esté activo | Respaldo manual y checksum | Trío `.sql`, `.sha256`, `.metadata.json` |
| Mensual o antes de un hito | `test-backup-restore.sh` | Cantidad de tablas restauradas |
| En una ventana coordinada | `test-persistence.sh` | Respaldo previo y auditoría posterior |

La retención provisional sugerida es conservar cuatro respaldos semanales y
tres mensuales. Es una propuesta técnica, no una política aprobada. No se debe
automatizar la eliminación hasta que la Universidad y la contraparte acuerden
retención, responsable y ubicación externa.

## Respaldo y restauración

Crear un respaldo adicional:

```bash
~/luzparral/scripts/backup-database.sh \
  staging \
  ~/luzparral/config/mysql.env \
  ~/luzparral/backups
```

Ensayar su restauración en una base temporal, sin alterar staging:

```bash
~/luzparral/scripts/test-backup-restore.sh \
  staging \
  ~/luzparral/backups/staging-FECHA.sql
```

Un respaldo almacenado solo en Parra protege frente a errores lógicos, pero no
frente a la pérdida completa del servidor. La copia externa cifrada y su
custodia siguen pendientes de una decisión institucional.

## Diagnóstico de incidentes

1. Ejecutar `operational-check.sh` y conservar la salida.
2. Revisar `podman ps -a` y los últimos logs sin copiar secretos:
   `podman logs --since 15m luzparral-staging`.
3. Si falla MySQL, revisar `database.sh status`; nunca publicar `3306`.
4. Si el servidor responde por SSH pero no desde el navegador, comprobar el
   acceso mediante túnel. No cambiar a un puerto no asignado.
5. Registrar fecha, entorno, imagen, síntoma, identificador de diagnóstico,
   acciones y resultado.
6. Escalar a la Universidad cuando el problema sea ingreso de red, DNS, HTTPS,
   certificado, reinicio del host o suspensión de la cuenta.

Para acceso temporal autorizado desde un equipo con SSH:

```powershell
ssh -N -L 127.0.0.1:28004:127.0.0.1:8004 USUARIO@parra.chillan.ubiobio.cl
```

Mientras el túnel esté abierto, staging se consulta en
`http://127.0.0.1:28004`. Esto no convierte `8004` en acceso público.

## Registro mínimo de una intervención

Cada despliegue o incidente debe registrar:

- fecha, responsable y entorno;
- commit, tag e identificador SHA-256;
- respaldo previo utilizado;
- resultado de `operational-check.sh`;
- cambio realizado y forma de reversión;
- dependencia institucional pendiente, cuando corresponda.

Las contraseñas, archivos `.env`, claves SSH y datos de clientes nunca forman
parte de la evidencia.