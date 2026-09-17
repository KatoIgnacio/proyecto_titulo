# Arquitectura y modelo de información vigentes

Este documento representa la solución implementada al cierre local del
Segmento 13. Complementa el diseño conceptual del anteproyecto con las
decisiones necesarias para seguridad, trazabilidad, importación y despliegue.

## Vista de componentes

```mermaid
flowchart LR
    U[Usuario autenticado] --> B[React + Inertia + Leaflet]
    B --> R[Rutas y middleware Laravel]
    R --> C[Controladores y validación de solicitudes]
    C --> S[Servicios de dominio y consultas]
    S --> DB[(MySQL)]
    S --> FS[(Almacenamiento privado de evidencias)]
    B -. teselas HTTPS .-> OSM[OpenStreetMap]
    B -. marco aislado HTTPS .-> W[Windy]
    F[Archivo sintético autorizado] --> I[Previsualización e importación transaccional]
    I --> DB
    P[Docker local / Podman Parra] --> R
```

La aplicación conserva un único backend Laravel. La interfaz no decide los
permisos: autenticación, roles, estados y acceso a información protegida se
validan nuevamente en el servidor. MySQL se ejecuta como servicio separado y
no forma parte de la imagen de aplicación.

## Correspondencia con los servicios del anteproyecto

| Servicio esperado | Componente vigente |
|---|---|
| Acceso y usuarios | Autenticación Laravel, sesiones en base de datos, rol y cuenta activa |
| Gestión de contingencias | Controladores de detalle y estado, reglas de transición y transacciones |
| Consulta y filtros | Consultas paginadas, filtros comunes y rangos calendario |
| Dashboard | Agregaciones operativas, evolución y distribución comunal |
| Georreferenciación | Leaflet, consulta por área visible y agrupación de marcadores |
| Reportes de terreno | Antecedentes, georreferencia opcional y archivos privados |
| Informes | Consulta consolidada y exportaciones CSV/PDF en tres modalidades |
| Importación | Previsualización, validación, lotes, errores y persistencia atómica |

## Modelo entidad-relación implementado

```mermaid
erDiagram
    USERS ||--o{ CONTINGENCIES : crea
    USERS ||--o{ CONTINGENCY_HISTORY : registra
    USERS ||--o{ FIELD_REPORTS : informa
    USERS ||--o{ IMPORT_BATCHES : importa
    COMMUNES ||--o{ FEEDERS : contiene
    COMMUNES ||--o{ SUPPLY_POINTS : ubica
    COMMUNES ||--o{ CONTINGENCIES : ubica
    FEEDERS ||--o{ SUPPLY_POINTS : alimenta
    FEEDERS ||--o{ CONTINGENCIES : relaciona
    IMPORT_BATCHES ||--o{ IMPORT_ERRORS : registra
    IMPORT_BATCHES ||--o{ CONTINGENCIES : origina
    CONTINGENCIES ||--o{ CONTINGENCY_IMPACTS : afecta
    SUPPLY_POINTS ||--o{ CONTINGENCY_IMPACTS : recibe
    CONTINGENCIES ||--o{ CONTINGENCY_HISTORY : mantiene
    CONTINGENCIES ||--o{ FIELD_REPORTS : documenta
    FIELD_REPORTS ||--o{ FIELD_REPORT_ATTACHMENTS : adjunta

    USERS {
        bigint id PK
        string email UK
        string role
        boolean active
    }
    DATASET_METADATA {
        bigint id PK
        string dataset_key UK
        string generator_version
        int random_seed
        json parameters_json
    }
    COMMUNES {
        bigint id PK
        string code UK
        string name UK
        decimal center_lat
        decimal center_lon
    }
    FEEDERS {
        bigint id PK
        bigint commune_id FK
        string code UK
        string name
    }
    SUPPLY_POINTS {
        bigint id PK
        string synthetic_code UK
        string customer_code UK
        bigint commune_id FK
        bigint feeder_id FK
        string criticality
    }
    IMPORT_BATCHES {
        bigint id PK
        bigint imported_by FK
        string source_name
        string status
        int accepted_rows
        int rejected_rows
    }
    IMPORT_ERRORS {
        bigint id PK
        bigint import_batch_id FK
        int source_row_number
        string error_code
    }
    CONTINGENCIES {
        bigint id PK
        string code UK
        string osf_code UK
        bigint commune_id FK
        bigint feeder_id FK
        bigint source_batch_id FK
        string status
        string priority
        datetime started_at
    }
    CONTINGENCY_IMPACTS {
        bigint id PK
        bigint contingency_id FK
        bigint supply_point_id FK
        string status
        int outage_minutes
    }
    CONTINGENCY_HISTORY {
        bigint id PK
        bigint contingency_id FK
        bigint user_id FK
        string status
        datetime event_at
        string source
    }
    FIELD_REPORTS {
        bigint id PK
        bigint contingency_id FK
        bigint reported_by FK
        string progress_status
        datetime observed_at
    }
    FIELD_REPORT_ATTACHMENTS {
        bigint id PK
        bigint field_report_id FK
        string path UK
        string mime_type
        string checksum_sha256
    }
```

`DATASET_METADATA` identifica el conjunto sintético y no necesita una relación
operacional con las demás tablas. Las tablas internas de sesiones, caché y
trabajos de Laravel se omiten del diagrama porque no forman parte del dominio.

## Evolución respecto del modelo conceptual

- El punto de suministro contiene códigos sintéticos de cliente y suministro;
  no existe una entidad con datos personales reales en el prototipo.
- La falla se representa mediante la contingencia, su causa, estado e impactos,
  evitando una duplicación sin uso funcional.
- La bitácora histórica, los antecedentes de terreno y sus evidencias se
  modelan explícitamente para satisfacer trazabilidad.
- Los lotes y errores de importación conservan el origen y permiten auditar
  aceptaciones y rechazos.
- Los conteos críticos y electrodependientes se exponen de forma agregada; los
  perfiles sin autorización no acceden a identificadores protegidos.

## Escalabilidad prevista

1. Las fuentes futuras PowerOn o CIOP deben ingresar mediante adaptadores que
   traduzcan sus formatos al modelo validado, sin acoplar controladores a una
   fuente particular.
2. La base MySQL separada permite cambiar host, respaldo y capacidad sin
   reconstruir la imagen de aplicación.
3. El mapa consulta solo el área visible y agrupa puntos cercanos para evitar
   enviar todo el universo al navegador.
4. Las exportaciones PDF pueden migrarse a una cola de trabajos si aumenta su
   concurrencia; las consultas ordinarias ya se validan con 30 sesiones.
5. OpenStreetMap y Windy permanecen aislados: su indisponibilidad degrada solo
   la cartografía base o el pronóstico, no el registro almacenado en MySQL.
