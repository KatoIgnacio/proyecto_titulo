# Arquitectura, clases y recorrido de datos

Este documento explica el recorrido técnico de SIGCEL desde la interacción de
la persona usuaria hasta MySQL y de regreso a la interfaz. Complementa el
modelo entidad-relación de `ARQUITECTURA_MODELO_ACTUAL.md`; no reemplaza el
código ni presenta componentes que no estén implementados.

## Responsabilidades por capa

| Capa | Ubicación | Responsabilidad |
| --- | --- | --- |
| Interfaz | `resources/js/Pages` y `resources/js/Components` | Formularios, filtros, tablas, gráficos y navegación con React e Inertia. |
| Entrada HTTP | `routes/web.php` y `routes/auth.php` | Relacionar URL y método HTTP con middleware y controlador. |
| Seguridad | middleware y `app/Http/Requests` | Autenticar, verificar cuenta activa, autorizar por rol y validar entradas. |
| Aplicación | `app/Http/Controllers` | Coordinar la solicitud y preparar la respuesta Inertia, JSON, CSV o PDF. |
| Dominio | `app/Services`, `app/Support` y `app/Enums` | Transacciones, estados, importación, mapas, informes y reglas reutilizables. |
| Persistencia | `app/Models` y `database/migrations` | Consultas Eloquent, relaciones, restricciones e índices de MySQL. |
| Presentación final | Inertia y React | Recibir propiedades serializadas y actualizar la vista sin duplicar reglas de seguridad. |

La interfaz puede ocultar acciones por usabilidad, pero el permiso efectivo se
comprueba nuevamente en Laravel. Las consultas variables se construyen con
Eloquent o Query Builder, que enlazan los parámetros; no se concatena SQL
recibido desde formularios.

## Diagrama de clases principales

```mermaid
classDiagram
    class DashboardController {
        +__invoke(Request) Response
        -filteredQuery(filters, referenceDate) Builder
    }
    class ContingencyStatusController {
        +update(request, contingency, transition) RedirectResponse
    }
    class ContingencyImportController {
        +index(request, manager) Response
        +preview(request, manager) RedirectResponse
        +store(request, importer) RedirectResponse
    }
    class ContingencyReportController {
        +index(request) InertiaResponse
        +exportCsv(request) StreamedResponse
        +exportPdf(request) HttpResponse
    }
    class ContingencyMapController {
        +__invoke(request, mapData) Response
        +data(request, mapData) JsonResponse
    }
    class TransitionContingencyStatus {
        +handle(contingency, target, user, note, eventAt) Contingency
    }
    class ImportContingencies {
        +execute(adapterKey, preview, user) ImportBatch
    }
    class ContingencyReportData {
        +filteredQuery(filters, referenceDate) Builder
        +summary(query) array
        +reportCollection(filters) Collection
        +analytics(contingencies, filters) array
    }
    class ContingencyMapData {
        +build(filters, referenceDate, bounds, zoom) array
    }
    class Contingency {
        +commune()
        +feeder()
        +impacts()
        +history()
        +fieldReports()
    }
    class ContingencyHistory
    class ContingencyImpact
    class Commune
    class Feeder
    class SupplyPoint
    class ImportBatch
    class User

    DashboardController --> Contingency
    DashboardController --> Commune
    DashboardController --> Feeder
    ContingencyStatusController --> TransitionContingencyStatus
    TransitionContingencyStatus --> Contingency
    TransitionContingencyStatus --> ContingencyHistory
    TransitionContingencyStatus --> User
    ContingencyImportController --> ImportContingencies
    ImportContingencies --> ImportBatch
    ImportContingencies --> Contingency
    ContingencyReportController --> ContingencyReportData
    ContingencyReportData --> Contingency
    ContingencyMapController --> ContingencyMapData
    ContingencyMapData --> Contingency
    Contingency --> Commune
    Contingency --> Feeder
    Contingency --> ContingencyImpact
    ContingencyImpact --> SupplyPoint
```

## Secuencia de consulta del dashboard

```mermaid
sequenceDiagram
    actor U as Persona usuaria
    participant R as React/Inertia
    participant RT as Ruta y middleware
    participant C as DashboardController
    participant E as Eloquent/Query Builder
    participant DB as MySQL

    U->>R: Selecciona filtros y aplica
    R->>RT: GET /dashboard?filtros
    RT->>RT: auth + verified + active
    RT->>C: Request autorizada
    C->>C: Valida rango, comuna, alimentador, estado y búsqueda
    C->>E: Construye una consulta base reutilizable
    E->>DB: SELECT parametrizados y agregaciones
    DB-->>E: Filas y totales
    E-->>C: Modelos y colecciones
    C-->>R: Respuesta Inertia con métricas y una página de 10 filas
    R-->>U: Actualiza panel, gráficos, tabla y navegación
```

Todos los indicadores parten de la misma consulta filtrada. La distribución
por comuna y por estado usa agregaciones; la tabla pagina todos los resultados
en grupos de diez y conserva los filtros al navegar. La búsqueda e informes
cubren consultas especializadas y exportaciones.

## Secuencia de cambio de estado

```mermaid
sequenceDiagram
    actor O as Perfil operacional
    participant F as Formulario React
    participant M as Middleware de rol
    participant V as UpdateContingencyStatusRequest
    participant S as TransitionContingencyStatus
    participant DB as MySQL

    O->>F: Selecciona nuevo estado y confirma
    F->>M: PATCH /contingencias/{id}/estado
    M->>V: Comprueba rol y cuenta activa
    V->>V: Valida estado, nota y fecha
    V->>S: Datos validados
    S->>DB: Inicia transacción y bloquea contingencia
    S->>S: Verifica transición permitida
    S->>DB: Actualiza contingencia y crea historial
    DB-->>S: Commit
    S-->>F: Redirección con resultado
```

La secuencia válida es `reported` → `assigned` → `in_progress` → `restored`
→ `closed`. El servicio usa transacción y bloqueo para evitar actualizaciones
simultáneas incoherentes.

## Secuencia de importación

```mermaid
sequenceDiagram
    actor S as Supervisión o administración
    participant UI as Importaciones
    participant C as ContingencyImportController
    participant A as SyntheticCsvContingencyAdapter
    participant I as ImportContingencies
    participant DB as MySQL

    S->>UI: Selecciona CSV sintético
    UI->>C: POST /importaciones/previsualizar
    C->>A: Lee, normaliza y valida filas
    A-->>UI: Resumen y errores sin persistir
    S->>UI: Confirma importación
    UI->>C: POST /importaciones
    C->>I: Ejecuta lote validado
    I->>DB: Transacción: lote, contingencias, historial y errores
    DB-->>I: Commit o rollback completo
    I-->>UI: Resultado aceptado/rechazado
```

## Flujo de informes

```mermaid
flowchart LR
    F[Filtros del informe] --> V[ContingencyReportRequest]
    V --> Q[ContingencyReportData]
    Q --> DB[(MySQL)]
    DB --> Q
    Q --> W[Vista Inertia]
    Q --> C[CSV transmitido]
    Q --> P[PDF con tablas, gráficos y mapa]
```

## Cómo modificar una función sin romper el recorrido

1. Identificar la página React y la ruta que recibe la acción.
2. Revisar middleware y rol antes de cambiar la visibilidad del control.
3. Ajustar o crear un `FormRequest` para validar la entrada en el servidor.
4. Mantener el controlador como coordinador; ubicar reglas transaccionales o
   reutilizables en un servicio.
5. Modificar modelos o migraciones solo si cambia la persistencia.
6. Agregar o actualizar la prueba de característica correspondiente.
7. Ejecutar `php artisan test` y `npm run build` antes de desplegar.
8. Verificar staging con `verify.sh` y `operational-check.sh`.
