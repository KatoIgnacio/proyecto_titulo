# Matriz de trazabilidad académica

Esta matriz relaciona el anteproyecto aprobado con la implementación y la
evidencia verificable de SIGCEL Luzparral. Los estados no sustituyen la
evaluación del profesor guía ni la validación con usuarios.

## Criterio de estado

- **Verificado en local:** existe implementación y evidencia automatizada o
  reproducible en el entorno local con datos sintéticos.
- **Preparado:** el procedimiento está definido, pero falta ejecutarlo con los
  participantes o en la infraestructura correspondiente.
- **Pendiente externo:** depende de usuarios, autorizaciones o del servidor
  institucional Parra.

## Objetivos

| Objetivo del anteproyecto | Requisitos relacionados | Evidencia principal | Estado |
|---|---|---|---|
| Objetivo general: diseñar e implementar un sistema que centralice, complemente y visualice información para apoyar la gestión de contingencias | RF01-RF09, RNF01-RNF07, HU01-HU09 | Módulos operativos, pruebas funcionales, integración Docker y documentación de los segmentos | Verificado en local; despliegue institucional pendiente |
| OE1: identificar flujos, usuarios y puntos críticos del proceso actual | RF01, RF05, RF06, RF07; HU01, HU05, HU06, HU07 | Levantamiento incorporado en el anteproyecto, diagnóstico operativo, roles y flujos implementados | Documentado e incorporado al diseño |
| OE2: definir el modelo de información y los requisitos | Todos los RF, RNF y HU | Modelo vigente, migraciones, validación de esquema y esta matriz | Verificado en local |
| OE3: implementar centralización, consulta, georreferenciación, trazabilidad e informes | RF01-RF09; HU01-HU09 | Aplicación ejecutable, rutas, pruebas de características y ensayo integral | Verificado en local |
| OE4: evaluar el sistema con usuarios involucrados | RNF05 y HU01-HU09 según rol | Protocolo y plantillas de `VALIDACION_USUARIOS.md` | Preparado; resultados reales pendientes |

## Requisitos funcionales e historias de usuario

| ID | HU | Alcance implementado | Pantalla o ruta | Evidencia automatizada | Estado |
|---|---|---|---|---|---|
| RF01 | HU01 | Importación controlada, previsualización, rechazo de duplicados y trazabilidad de lote | `/importaciones` | `ContingencyImportTest` | Verificado en local |
| RF02 | HU02 | Indicadores, evolución, comunas y listado consolidado | `/dashboard` | `DashboardTest` | Verificado en local |
| RF03 | HU03 | Filtros por día, mes, año, rango, comuna, alimentador, criticidad, estado y texto | Dashboard, mapa e informes | `CustomDateRangeTest`, `DashboardTest` | Verificado en local |
| RF04 | HU04 | Mapa por área visible, agrupación de puntos y capas agregadas protegidas | `/contingencias/mapa` | `ContingencyMapTest` | Verificado en local |
| RF05 | HU05 | Búsqueda paginada por contingencia, OSF, cliente y suministro sintéticos | `/buscador-operacional` | `OperationalSearchTest` | Verificado en local |
| RF06 | HU06 | Registro de antecedentes de terreno y evidencias privadas | Detalle de contingencia | `FieldReportTest` | Verificado en local |
| RF07 | HU07 | Secuencia de estados, control de concurrencia y bitácora histórica | Detalle de contingencia | `ContingencyStatusTransitionTest` | Verificado en local |
| RF08 | HU08 | Informe completo, resumen gráfico y evolución; exportación CSV y PDF | `/informes` | `ContingencyReportTest` | Verificado en local |
| RF09 | HU09 | Autenticación, cuentas activas y permisos por rol aplicados en backend | `/login` y módulos protegidos | `AuthenticationTest`, `RoleAuthorizationTest`, `ProductionSecurityTest` | Verificado en local |

## Requisitos no funcionales

| ID | Criterio | Evidencia | Estado y conclusión |
|---|---|---|---|
| RNF01 | Consultas, filtros y registros ordinarios en un máximo de 3 segundos | Ensayo HTTP con 5, 10 y 30 sesiones; peor P95 ordinario: 392,45 ms | Verificado en local |
| RNF02 | Disponibilidad mientras la infraestructura esté operativa | `/up`, comando de salud, manejo controlado de base no disponible e integración Docker | Verificado en local; disponibilidad en Parra pendiente |
| RNF03 | Hasta 30 usuarios concurrentes manteniendo RNF01 | 360 solicitudes ordinarias con 30 sesiones, cero fallos y P95 máximo de 392,45 ms | Verificado en local |
| RNF04 | Acceso autenticado y protegido | Pruebas de autenticación, sesión, encabezados y roles | Verificado en local |
| RNF05 | Al menos 80 % de tareas principales sin ayuda directa del desarrollador | Guion, métrica y plantillas de evaluación | Preparado; no se declara cumplimiento sin participantes reales |
| RNF06 | Trazabilidad de modificaciones | Historial atómico de estados, usuario responsable y antecedentes de terreno | Verificado en local |
| RNF07 | Actualización y consistencia de la información | Transacciones de importación, prevención de duplicados y validaciones de integridad | Verificado en local |

Las exportaciones PDF son operaciones intensivas y se miden por separado con un
máximo local de diez usuarios simultáneos. No se usan para concluir RNF01 ni
RNF03, cuyos criterios se refieren a consulta, filtrado y registro ordinarios.

## Comandos de evidencia

```powershell
pwsh -NoProfile -File .\deploy\VALIDAR_INTEGRACION_LOCAL.ps1 -RunPerformance
php artisan test
npm run build
vendor\bin\pint --test
composer validate --strict
```

El resultado detallado de rendimiento se genera en
`storage/app/quality/segment-13-performance.json`; se conserva como evidencia
local y no se versiona porque contiene datos del entorno de ejecución.
