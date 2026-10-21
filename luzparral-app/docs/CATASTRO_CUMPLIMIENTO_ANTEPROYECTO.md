# Catastro de cumplimiento del anteproyecto

El cierre local y el staging institucional demuestran que SIGCEL implementa el
núcleo funcional definido en el anteproyecto. El resultado no debe presentarse
como despliegue productivo ni como validación definitiva con usuarios: la
promoción a producción y la evaluación empírica permanecen pendientes.

## Resultado ejecutivo

| Área | Resultado actual |
|---|---|
| RF01-RF09 y HU01-HU09 | Implementados y cubiertos por pruebas locales |
| RNF01 | Cumplido en el ensayo local: peor P95 ordinario de 392,45 ms frente a 3.000 ms |
| RNF02 | Staging Parra saludable; restauración y reinicio de contenedores aprobados; acceso externo y reinicio del servidor pendientes |
| RNF03 | Cumplido en local con 30 sesiones concurrentes, cero fallos ordinarios |
| RNF04 | Autenticación y autorización verificadas en backend |
| RNF05 | Protocolo completo; medición real del umbral de 80 % pendiente |
| RNF06-RNF07 | Trazabilidad, consistencia e importación atómica verificadas |
| OE1-OE3 | Materializados en levantamiento, modelo, requisitos e implementación |
| OE4 | Preparado; exige sesiones reales autorizadas con usuarios |
| Despliegue Parra | `c439b2f7f840` operativo y saludable en `8004`; reinicio completo del servidor y producción `8003` pendientes |

La relación detallada entre cada requisito, pantalla, prueba y evidencia se
encuentra en `MATRIZ_TRAZABILIDAD_ACADEMICA.md`.

## Evidencia local del Segmento 28

El 21 de octubre de 2026 se atendieron los hallazgos del QA técnico informal
del staging. El acceso ahora considera los valores efectivos completados por el
navegador y comunica el envío; los mensajes de autenticación y validación están
en español; la gestión temporal diferencia el nombre visible del correo usado
para ingresar y dispone de copia alternativa; el CSV se descarga sin abandonar
Informes; y Perfil quedó completamente en español. Una prueba de regresión
confirma además que Administración lista las cuentas temporales creadas por
autorregistro.

La validación local aprobó 163 pruebas con 1.883 aserciones, sin fallos ni
errores, además de Pint y la construcción de producción de Vite. El detalle y
los criterios para repetir la comprobación se encuentran en
`RESULTADO_QA_TECNICO_20261020.md`. Estas correcciones permanecen pendientes de
despliegue y repetición manual en staging; por ello no constituyen todavía
aceptación con usuarios ni cumplimiento de RNF05.

## Evidencia local del Segmento 27

El 9 de octubre de 2026 se incorporó el autorregistro desde la pantalla inicial.
El formulario público solo puede crear cuentas temporales con el rol Consulta,
vigencia de siete días y contraseña robusta; ignora cualquier intento de
solicitar un rol superior, aplica limitación de frecuencia y respeta el máximo
institucional de diez cuentas activas. Administración conserva la creación y
desactivación controlada de cuentas temporales desde `/cuentas-consulta`.

La validación local aprobó 160 pruebas con 1.848 aserciones, Pint y la
construcción de producción de Vite. El cambio fue desplegado mediante
`luzparral-app:c439b2f7f840`; las verificaciones de aplicación, contenedor,
base, esquema y auditoría operativa terminaron correctamente en staging
`8004`.

## Evidencia local del Segmento 26

El 8 de octubre de 2026 se eliminó la diferencia funcional entre Consulta y
Visita. Existe un único rol Consulta: una cuenta puede ser permanente o tener
vencimiento automático, pero conserva exactamente los mismos permisos. Consulta
puede revisar panel, búsqueda, detalle e informes; el backend bloquea mapa,
pronóstico, importaciones, identificadores sensibles y cualquier modificación.

Una migración convierte las cuentas históricas con rol `guest` a `viewer`.
Administración solo puede desactivar desde la pantalla temporal las cuentas de
Consulta que tengan vencimiento, por lo que la cuenta permanente queda
protegida. La validación local aprobó 157 pruebas con 1.823 aserciones, Pint y
la construcción de producción de Vite. El cambio fue desplegado el 8 de octubre
de 2026 mediante `luzparral-app:83690e39367d`; la migración se ejecutó y las
verificaciones de aplicación, contenedor, base, esquema, roles y auditoría
operativa finalizaron correctamente.

## Evidencia técnica del Segmento 25

El 6 de octubre de 2026 se completó la implementación y verificación local de
tres extensiones para la presentación académica. Administración puede crear y
desactivar cuentas temporales con el mismo rol Consulta; la credencial se muestra una sola
vez, vence automáticamente y respeta el límite global de diez cuentas activas.
La cuenta temporal dispone de panel, búsqueda, detalle e informes en modo de consulta,
pero el backend le impide acceder al mapa, al pronóstico, a importaciones, a
identificadores sensibles y a operaciones de modificación.

Los informes incorporan filtros independientes para contingencias con o sin
clientes críticos y electrodependientes. Los tres tipos de PDF incluyen un mapa
esquemático construido localmente con coordenadas sintéticas, sin solicitar
teselas externas. Un informe integral de cinco páginas fue renderizado e
inspeccionado sin cortes, superposiciones ni datos personales.

La validación local aprobó 155 pruebas con 1.840 aserciones, la construcción de
producción de Vite y Pint. El 6 de octubre de 2026 se desplegó en staging la
imagen `luzparral-app:3ebc513c86fb`; la migración de ciclo de vida de cuentas
terminó correctamente y `verify.sh staging --database` confirmó aplicación,
contenedor, MySQL y esquema saludables en el puerto `8004`.

## Evidencia técnica del Segmento 24

El 5 de octubre de 2026 se completó la aceptación funcional técnica de staging
con las cinco cuentas sintéticas. AF01 a AF14 terminaron OK: se verificaron
módulos comunes, restricciones de identificadores, informes, importaciones,
cambios de estado, antecedentes de terreno y respuestas 403 para acciones no
autorizadas. La incidencia INC-AF-01 de credencial divergente en S01 se resolvió
mediante mantenimiento controlado limitado a las cinco cuentas sintéticas, sin
exponer secretos, y los cinco accesos se comprobaron nuevamente.

La ejecución utilizó luzparral-app:ef209b7d3d9e en 8004. Antes de las pruebas
mutables se creó y verificó staging-20261005T230556Z.sql. La auditoría posterior
aprobó aplicación, contenedor, base privada, esquema, encabezados, persistencia,
uso de disco y puertos asignados. El resultado completo y sus 35 comprobaciones
estructuradas están en RESULTADO_ACEPTACION_FUNCIONAL_STAGING_20261005.md y
evidence/aceptacion_funcional_staging-20261005.csv. OE4 y RNF05 permanecen
pendientes de evaluación con participantes reales.

## Evidencia técnica del Segmento 23

El 1 de octubre de 2026 staging se actualizó a
`luzparral-app:ef209b7d3d9e`, cuyo TAR `linux/amd64` fue comprobado con
SHA-256
`186f65f03ceb617c06fba7a1cfc4f5be6e1455174b242e86ce6d81191eab3cd1`.
`operational-check.sh` aprobó aplicación, MySQL, esquema, encabezados,
Podman rootless, `Linger=yes`, políticas de reinicio, volúmenes, puertos,
permisos, respaldo y espacio. El respaldo
`staging-20261001T231650Z.sql` se restauró en una base temporal y verificó
20 tablas. Después se creó `staging-20261001T231915Z.sql` y el reinicio
controlado de MySQL y la aplicación terminó con
`Prueba de persistencia de contenedores: OK`. No se reinició el servidor
completo.

## Evidencia técnica del Segmento 21

El 1 de octubre de 2026 se reconciliaron los puertos asignados por la
Universidad: staging `8004` y producción `8003`. Los scripts, plantillas,
pruebas y documentos quedaron alineados con Parra. La validación local aprobó
139 pruebas con 1.486 aserciones, la construcción Vite, `composer validate
--strict` y Pint.

## Evidencia técnica del Segmento 20

El 24 de septiembre de 2026 se repitió el ensayo integral con MySQL 8.4.11 y
una topología equivalente a Parra: base únicamente en red interna y aplicación
en redes de datos y entrada. Se aprobaron 139 pruebas con 1.482 aserciones, la
construcción de producción y el recorrido HTTP completo. El 29 de septiembre
de 2026 se verificó en Parra el staging `luzparral-app:4c9d0b8e1833` en `8004`,
con MySQL saludable en red privada, `3306` sin publicar y recorrido funcional
mediante túnel SSH. Falta confirmar la apertura externa, el reinicio del
servidor y la promoción controlada a producción.

## Evidencia técnica del Segmento 13

El ensayo integral del 17 de septiembre de 2026 utilizó MySQL 8.4.9 aislado y
un conjunto de demostración compuesto exclusivamente por datos sintéticos:

- 5 usuarios sintéticos: 1 de administración, 1 de supervisión, 2 de operación
  y 1 de consulta; además, 5 comunas, 10 alimentadores y 5.000 suministros;
- 360 contingencias, 25.283 impactos y 1.729 eventos de historial;
- 144 antecedentes de terreno y 18 lotes de importación;
- 20 controles de integridad aprobados;
- 5, 10 y 30 sesiones concurrentes para operaciones ordinarias;
- cero respuestas fallidas y P95 máximo ordinario de 392,45 ms a 30 sesiones.

CSV y PDF se midieron por separado hasta diez usuarios. El PDF ejecutivo fue
la operación más costosa, con P95 de 7.554,37 ms a diez sesiones, dentro de su
límite local de 12.000 ms. Esta observación justifica mantener como trabajo
futuro la generación asíncrona si crece la demanda.

## Resultados funcionales alcanzados

- Centralización, indicadores, evolución y distribución territorial.
- Calendario por día, mes, año o rango y filtros comunes entre módulos.
- Mapa escalable con selección, agrupación y capas agregadas protegidas.
- Búsqueda paginada por referencias operativas y códigos sintéticos.
- Detalle con evolución temporal, cambio de estado y antecedentes de terreno;
  Administración puede corregirlos o eliminarlos con confirmación y auditoría.
- Importación previsualizada, transaccional y auditable, con resultados
  parciales y causas de rechazo explicadas en la interfaz.
- Informes completo, resumen gráfico y evolución, con filtros por clientes críticos y electrodependientes, mapa esquemático y exportación filtrada.
- Roles en backend, cuentas activas, consultas temporales con vencimiento, archivos privados y diagnóstico de salud.
- Contenedorización reproducible y proceso de promoción 8004 a 8003 con
  comprobación de integridad y reversión.

## Limitaciones declaradas

- El prototipo utiliza datos sintéticos y no incluye información personal u
  operacional real de la empresa.
- No existe sincronización automática con PowerOn, CIOP u otra fuente externa;
  la integración futura debe usar adaptadores autorizados.
- No ejecuta maniobras ni controla la red eléctrica.
- La salud de aplicación y base se verificó en Parra; la disponibilidad desde
  redes externas, la latencia institucional y la persistencia tras reiniciar
  el servidor aún requieren coordinación con la Universidad.
- OpenStreetMap y Windy dependen de acceso HTTPS externo y pueden degradarse de
  forma independiente.
- RNF05 y OE4 no están cumplidos hasta ejecutar la evaluación autorizada con
  usuarios y obtener al menos 80 % de tareas principales sin ayuda directa.
- El despliegue en la empresa queda posterior a la aprobación académica y a
  los acuerdos de seguridad, operación y tratamiento de datos.

## Trabajo futuro priorizado

1. Ejecutar el protocolo de usuarios, consolidar resultados y corregir los
   hallazgos críticos antes de cerrar OE4 y RNF05.
2. Confirmar en staging `8004` el acceso externo y la persistencia tras
   reiniciar el servidor; recién después de la aceptación promover a `8003`.
3. Incorporar adaptadores de fuentes reales solo con autorización, diccionario
   de datos, reglas de calidad y resguardo de información definidos.
4. Mover exportaciones PDF a una cola si la medición institucional o el volumen
   futuro lo requieren.
5. Acordar respaldo, monitoreo, HTTPS, retención de logs y responsables de
   operación antes de cualquier uso productivo.

## Conclusión

El anteproyecto está ampliamente cubierto desde la perspectiva de construcción
y verificación local. La afirmación académicamente correcta es: **núcleo
funcional verificado en local y staging institucional operativo; evaluación
empírica con usuarios, acceso externo y producción pendientes**.
