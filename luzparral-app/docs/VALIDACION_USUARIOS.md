# Protocolo de validación con usuarios

Este protocolo permite evaluar el objetivo específico 4 y RNF05 sin confundir
una demostración informal con evidencia de usabilidad. No contiene resultados:
el porcentaje solo se calculará después de realizar sesiones autorizadas con
participantes reales.

## Participantes y resguardo

Se consideran personas adultas vinculadas al proceso de gestión de
contingencias, seleccionadas por función y con autorización de la organización.
Antes de grabar una reunión se debe obtener consentimiento. En el repositorio
solo se registran códigos seudónimos (`P01`, `P02`, etc.), rol general y
resultados agregados; no se almacenan nombres, RUT, correos, audios ni datos
operacionales reales.

Las grabaciones y los correos electrónicos se conservan fuera de Git conforme
a la autorización ética y a la política institucional. Los comentarios
informales no se omiten: se registran como notas de campo, seudonimizadas y con
su contexto, y se confirman con la persona antes de convertirlos en una
decisión de producto.

## Tareas principales

Cada participante ejecuta solo las tareas compatibles con su rol.

| Código | Tarea observable | Resultado esperado |
|---|---|---|
| T01 | Iniciar sesión y reconocer el estado general | Accede al panel e identifica una contingencia activa |
| T02 | Filtrar un período por día, mes, año o rango | Obtiene el período solicitado y reconoce los filtros aplicados |
| T03 | Explorar el mapa y seleccionar un evento | Ubica una contingencia, interpreta la agrupación y abre su detalle |
| T04 | Buscar por contingencia, OSF, cliente o suministro sintético | Encuentra el antecedente respetando los permisos del rol |
| T05 | Revisar el detalle y su evolución temporal | Identifica estado, impacto, antecedentes e historial |
| T06 | Registrar un cambio de estado | Completa una transición válida y verifica su trazabilidad |
| T07 | Registrar un antecedente de terreno | Guarda el avance y, si corresponde, una evidencia permitida |
| T08 | Elegir y exportar un tipo de informe | Genera el reporte adecuado: completo, resumen gráfico o evolución |
| T09 | Previsualizar una importación sintética | Reconoce registros aceptados y rechazados antes de confirmar |

## Procedimiento

1. Explicar el propósito, el carácter académico y el uso exclusivo de datos
   sintéticos; solicitar el consentimiento correspondiente.
2. Asignar un código de participante y registrar únicamente su rol general.
3. Entregar la consigna de cada tarea sin indicar los pasos de interfaz.
4. Observar la ejecución, tiempo, dudas y errores. La ayuda directa del
   desarrollador solo se entrega si la persona no puede continuar y queda
   marcada como tal.
5. Recoger una apreciación breve sobre claridad, utilidad, confianza y mejoras.
6. Consolidar los hallazgos, acordar prioridades y conservar una referencia a
   la evidencia autorizada fuera del repositorio.

## Cálculo de RNF05

Para cada intento se registra una sola condición: completado sin asistencia
directa, completado con asistencia o no completado.

```text
Porcentaje sin asistencia =
  tareas completadas sin asistencia directa / tareas asignadas válidas * 100
```

RNF05 se acepta únicamente si el resultado global es igual o superior a 80 %.
También se informa el resultado por tarea y por rol para evitar que el promedio
oculte problemas importantes. Un error técnico invalida ese intento y exige
repetirlo después de corregir la causa.

## Sistematización de fuentes

| Canal | Registro permitido en Git | Evidencia original | Tratamiento |
|---|---|---|---|
| Reuniones grabadas | Código, síntesis, tarea relacionada y decisión | Grabación autorizada fuera del repositorio | Revisar, codificar y mantener referencia trazable |
| Correo electrónico | Paráfrasis y código de referencia | Correo conservado por canal institucional | Confirmar alcance antes de implementar |
| Comentarios informales | Nota de campo con fecha, contexto y código seudónimo | Confirmación posterior de la persona | No omitir; distinguir sugerencia de requisito aprobado |

Se utilizan las plantillas
`templates/validacion_usuarios_resultados.csv` y
`templates/retroalimentacion.csv`. Las filas deben contener códigos, nunca
identificadores personales.

## Antecedentes que deben respaldarse

Las siguientes decisiones provienen de conversaciones previas y son útiles
como antecedentes, pero no cuentan por sí solas como la validación formal de
RNF05:

| Código | Antecedente | Situación en el prototipo | Evidencia por asociar |
|---|---|---|---|
| FB-01 | Filtro calendario por día, mes, año y rango | Implementado | Minuta, grabación o correo autorizado |
| FB-02 | Tres modalidades de informe | Implementado | Minuta, grabación o correo autorizado |
| FB-03 | Informes con evolución, gráficos y comunas afectadas | Implementado | Minuta, grabación o correo autorizado |
| FB-04 | Uso de identidad visual de SIGCEL y Luzparral | Implementado | Aprobación o comentario confirmado |
| FB-05 | Marcadores agrupados según concentración de eventos | Implementado | Validación de comprensión en T03 |

## Cierre de la evaluación

El informe de resultados debe indicar muestra, tareas asignadas, porcentaje
global y por tarea, observaciones cualitativas, cambios aceptados o descartados,
limitaciones y referencias de evidencia. Hasta completar este proceso, OE4 y
RNF05 permanecen **preparados y pendientes de validación real**.
