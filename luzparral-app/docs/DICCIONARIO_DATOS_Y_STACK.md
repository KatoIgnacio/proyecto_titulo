# Diccionario de datos y stack tecnológico

## Diccionario de entidades del dominio

Los códigos son identificadores documentales breves; los nombres físicos
corresponden a las migraciones vigentes.

| Código | Nombre | Tabla | Detalle |
| --- | --- | --- | --- |
| DAT | Metadatos sintéticos | `dataset_metadata` | Versión, semilla y parámetros que permiten identificar el conjunto de demostración. |
| COM | Comuna | `communes` | Catálogo territorial con código, nombre, centro cartográfico y vigencia. |
| ALI | Alimentador | `feeders` | Circuito asociado a una comuna; permite filtrar contingencias y suministros. |
| SUM | Punto de suministro sintético | `supply_points` | Identificador ficticio, ubicación, alimentador, criticidad y estado activo. No almacena identidad real. |
| IMP | Lote de importación | `import_batches` | Origen, archivo sintético, estado, cantidades y responsable de una carga. |
| ERR | Error de importación | `import_errors` | Fila, campo, código y mensaje de rechazo asociados a un lote. |
| CON | Contingencia | `contingencies` | Evento central: código, OSF, comuna, alimentador, estado, prioridad, causa, fechas, ubicación y afectados agregados. |
| AFE | Impacto | `contingency_impacts` | Vincula una contingencia con un suministro y registra afectación, reposición y duración. |
| HIS | Historial de contingencia | `contingency_history` | Bitácora de estado, nota, fecha, origen y usuario responsable. |
| TER | Antecedente de terreno | `field_reports` | Avance observado, descripción, fecha, ubicación opcional y responsable. |
| ADJ | Evidencia de terreno | `field_report_attachments` | Archivo privado, nombre, tipo, tamaño y checksum SHA-256. |
| USR | Usuario | `users` | Nombre, correo, contraseña cifrada, rol, vigencia y vencimiento opcional. |

## Campos operacionales principales

| Código | Nombre | Tipo/valores | Detalle |
| --- | --- | --- | --- |
| CON.code | Código de contingencia | cadena única | Identificador visible sintético del evento. |
| CON.osf_code | Código OSF | cadena única | Referencia operacional ficticia. |
| CON.status | Estado | `reported`, `assigned`, `in_progress`, `restored`, `closed` | Ciclo controlado por transiciones consecutivas. |
| CON.priority | Prioridad | `critical`, `high`, `medium`, `low` | Criticidad operacional del evento. |
| CON.affected_total | Afectados | entero no negativo | Total agregado de clientes afectados. |
| CON.critical_affected | Clientes críticos | entero no negativo | Conteo agregado para análisis e informes. |
| CON.electrodependent_affected | Electrodependientes | entero no negativo | Conteo agregado; no identifica personas. |
| SUM.synthetic_code | Suministro sintético | cadena única | Código ficticio de suministro. |
| SUM.customer_code | Cliente sintético | cadena única | Código ficticio de cliente. |
| SUM.criticality | Clasificación | cadena controlada por la fuente | Clasificación operacional del suministro. |
| AFE.outage_minutes | Duración | entero nullable | Minutos calculados cuando existe reposición. |
| TER.progress_status | Avance | `inspection`, `repair`, `awaiting_resources`, `completed` | Estado del trabajo informado en terreno. |
| USR.role | Rol | `admin`, `supervisor`, `operator`, `viewer` | Administración, Supervisión, Operación o Consulta. |
| USR.active | Cuenta activa | booleano | Bloquea el acceso sin eliminar trazabilidad. |
| USR.expires_at | Vencimiento | fecha-hora nullable | Caducidad de autorregistros de Consulta. |

## Relaciones y reglas críticas

| Relación/regla | Implementación |
| --- | --- |
| Comuna 1:N alimentadores | `feeders.commune_id` referencia `communes.id`. |
| Contingencia N:1 comuna y alimentador | Claves foráneas `commune_id` y `feeder_id`. |
| Contingencia N:M suministros | Tabla intermedia con datos propios `contingency_impacts`. |
| Contingencia 1:N historial | Eliminación en cascada del historial al eliminar el evento. |
| Contingencia 1:N antecedentes | Los antecedentes y adjuntos conservan trazabilidad y almacenamiento privado. |
| Importación atómica | El servicio persiste el lote dentro de una transacción; ante error revierte la operación. |
| Estados secuenciales | No se permite saltar etapas ni reabrir una contingencia cerrada desde la interfaz normal. |
| Datos personales | El prototipo usa códigos sintéticos y conteos agregados; no debe cargarse información real identificable. |

## Matriz del stack tecnológico

| Componente | Versión/configuración vigente | Función | Factibilidad y condición de uso |
| --- | --- | --- | --- |
| PHP | 8.3 en la imagen desplegable | Runtime del backend | Disponible en contenedor Linux; extensiones fijadas en `Containerfile`. |
| Laravel | 12.x | MVC, autenticación, validación, Eloquent, sesiones y consola | Código abierto; centraliza seguridad y lógica servidor. |
| MySQL | 8.4.11 | Persistencia relacional | Servicio separado con volumen; en Parra no publica 3306. Revisar licencia de distribución si se entrega fuera del entorno institucional. |
| React | 18.x | Interfaz por componentes | Código abierto; compilado como recursos estáticos. |
| TypeScript | 5.x | Tipado del frontend | Reduce errores de contratos entre propiedades Inertia y componentes. |
| Inertia | 2.x | Puente Laravel-React | Evita una API duplicada para navegación interna; la autorización permanece en Laravel. |
| Vite | 6.x | Compilación frontend | Solo se requiere durante construcción/desarrollo; producción sirve recursos compilados. |
| Tailwind CSS | 3.x | Sistema visual | Clases utilitarias compiladas; no requiere servicio externo. |
| Leaflet / React-Leaflet | 1.9 / 4.2 | Cartografía interactiva | Código abierto; desacoplado de la fuente de teselas. |
| OpenStreetMap | teselas HTTPS externas | Cartografía base | Requiere red y respeto de atribución/política de uso; su caída no afecta MySQL. |
| Windy | marco HTTPS aislado | Pronóstico meteorológico referencial | Servicio externo; requiere red y condiciones del proveedor; no controla contingencias. |
| Dompdf | 3.1 | Exportación PDF | Se ejecuta dentro del backend sin enviar datos a terceros. |
| Docker Desktop | desarrollo Windows | Construcción de imágenes Linux | Herramienta local; no forma parte del runtime de Parra. |
| Podman rootless | staging institucional | Ejecución de contenedores | Reduce privilegios; usa volúmenes persistentes y redes privadas. |
| Apache | imagen PHP de producción | Servidor HTTP interno | Escucha en 8080 del contenedor; Parra publica 8004/8003. |
| Git/GitHub | rama `main` | Versionamiento y respaldo del código | No deben versionarse `.env`, claves, respaldos SQL ni datos sensibles. |

## Factibilidad del cambio

| Tipo de cambio | Archivos habituales | Impacto esperado |
| --- | --- | --- |
| Texto, orden o estilo | `resources/js/Pages`, `Components`, `Layouts` | Bajo; requiere `npm run build` y revisión visual. |
| Filtro o campo de formulario | React, ruta, `FormRequest`, controlador y prueba | Medio; debe conservar validación de servidor. |
| Regla operacional | `app/Enums` o `app/Services` y pruebas | Medio/alto; revisar transacciones, permisos e historial. |
| Campo persistente | Migración, modelo, servicio, interfaz y pruebas | Alto; exige respaldo y ensayo de migración/restauración. |
| Integración externa | Adaptador/servicio, configuración y diagnóstico | Alto; documentar disponibilidad, privacidad y degradación. |

Las versiones exactas resueltas se obtienen de `composer.lock`,
`package-lock.json` y las etiquetas de las imágenes. Esta tabla describe la
línea compatible declarada por el proyecto y no reemplaza esos archivos.
