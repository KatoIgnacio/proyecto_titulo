# Catastro de cumplimiento del anteproyecto

El cierre local demuestra que SIGCEL implementa el núcleo funcional definido
en el anteproyecto. El resultado no debe presentarse como despliegue productivo
ni como validación definitiva con usuarios: ambos hitos dependen de evidencia
externa que todavía no corresponde fabricar.

## Resultado ejecutivo

| Área | Resultado actual |
|---|---|
| RF01-RF09 y HU01-HU09 | Implementados y cubiertos por pruebas locales |
| RNF01 | Cumplido en el ensayo local: peor P95 ordinario de 392,45 ms frente a 3.000 ms |
| RNF02 | Salud y degradación comprobadas localmente; disponibilidad institucional pendiente |
| RNF03 | Cumplido en local con 30 sesiones concurrentes, cero fallos ordinarios |
| RNF04 | Autenticación y autorización verificadas en backend |
| RNF05 | Protocolo completo; medición real del umbral de 80 % pendiente |
| RNF06-RNF07 | Trazabilidad, consistencia e importación atómica verificadas |
| OE1-OE3 | Materializados en levantamiento, modelo, requisitos e implementación |
| OE4 | Preparado; exige sesiones reales autorizadas con usuarios |
| Despliegue Parra | Servidor inspeccionado y paquete con MySQL privado preparado; staging 2004 y producción 2003 pendientes |

La relación detallada entre cada requisito, pantalla, prueba y evidencia se
encuentra en `MATRIZ_TRAZABILIDAD_ACADEMICA.md`.

## Evidencia técnica del Segmento 20

El 24 de septiembre de 2026 se repitió el ensayo integral con MySQL 8.4.11 y
una topología equivalente a Parra: base únicamente en red interna y aplicación
en redes de datos y entrada. Se aprobaron 139 pruebas con 1.482 aserciones, la
construcción de producción y el recorrido HTTP completo. El servidor ya fue
confirmado como `x86_64`, Podman 5.8.2 rootless, `Linger=yes`, puertos libres y
espacio suficiente. Falta transferir y aceptar staging en el entorno real.

## Evidencia técnica del Segmento 13

El ensayo integral del 17 de septiembre de 2026 utilizó MySQL 8.4.9 aislado y
un conjunto de demostración compuesto exclusivamente por datos sintéticos:

- 5 usuarios por rol, 5 comunas, 10 alimentadores y 5.000 suministros;
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
- Informes completo, resumen gráfico y evolución, con exportación filtrada.
- Roles en backend, cuentas activas, archivos privados y diagnóstico de salud.
- Contenedorización reproducible y proceso de promoción 2004 a 2003 con
  comprobación de integridad y reversión.

## Limitaciones declaradas

- El prototipo utiliza datos sintéticos y no incluye información personal u
  operacional real de la empresa.
- No existe sincronización automática con PowerOn, CIOP u otra fuente externa;
  la integración futura debe usar adaptadores autorizados.
- No ejecuta maniobras ni controla la red eléctrica.
- La disponibilidad, latencia y persistencia tras reinicio de Parra solo pueden
  medirse en el servidor institucional.
- OpenStreetMap y Windy dependen de acceso HTTPS externo y pueden degradarse de
  forma independiente.
- RNF05 y OE4 no están cumplidos hasta ejecutar la evaluación autorizada con
  usuarios y obtener al menos 80 % de tareas principales sin ayuda directa.
- El despliegue en la empresa queda posterior a la aprobación académica y a
  los acuerdos de seguridad, operación y tratamiento de datos.

## Trabajo futuro priorizado

1. Ejecutar el protocolo de usuarios, consolidar resultados y corregir los
   hallazgos críticos antes de cerrar OE4 y RNF05.
2. Desplegar en staging institucional por el puerto 2004, completar la lista de
   aceptación y recién entonces promover al puerto 2003.
3. Incorporar adaptadores de fuentes reales solo con autorización, diccionario
   de datos, reglas de calidad y resguardo de información definidos.
4. Mover exportaciones PDF a una cola si la medición institucional o el volumen
   futuro lo requieren.
5. Acordar respaldo, monitoreo, HTTPS, retención de logs y responsables de
   operación antes de cualquier uso productivo.

## Conclusión

El anteproyecto está ampliamente cubierto desde la perspectiva de construcción
y verificación local. La afirmación académicamente correcta es: **núcleo
funcional y requisitos técnicos verificados en local; evaluación empírica con
usuarios y validación de infraestructura institucional pendientes**.
