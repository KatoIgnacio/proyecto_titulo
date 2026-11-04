# Seguimiento de revisión docente del 4 de noviembre de 2026

## Alcance

Este registro contrasta las observaciones entregadas sobre SIGCEL con el código
vigente. La revisión docente es evidencia de mejora y no reemplaza la validación
formal con usuarios ni demuestra por sí sola el cumplimiento de RNF05.

## Correcciones incorporadas

| Área | Corrección aplicada | Evidencia técnica |
| --- | --- | --- |
| Identidad | Favicon institucional y títulos sin la marca predeterminada de Laravel. | `resources/views/app.blade.php`, `resources/js/app.tsx` |
| Estructura | Pie institucional compacto en acceso y módulos autenticados. | `GuestLayout.tsx`, `AuthenticatedLayout.tsx` |
| Autorregistro | Campos obligatorios informados y corrección de `apóstrofos` en interfaz y validación. | `Register.tsx`, `RegisteredUserController.php` |
| Autorización | Consulta ya no recibe el enlace al mapa que terminaba en una respuesta 403. | `Contingencies/Show.tsx` |
| Dashboard | Búsqueda parcial explicada, eje vertical rotulado, descripción temporal corregida y orden reciente declarado. | `Dashboard.tsx` |
| Períodos | El rango inicial informa que parte en el primer día del mes del último dato disponible. | `PeriodFields.tsx` |
| Mapa | Orden de filtros consistente, recuperación visible sin eventos y cifras críticas/electrodependientes destacadas. | `Contingencies/Map.tsx` |
| Buscador | `Todo el registro` funciona sin texto; comuna y alimentador utilizan opciones acotadas. | `OperationalSearchController.php`, `Contingencies/Search.tsx` |
| Informes | Ejecutivo vertical, eje rotulado, imágenes sin deformación, tendencia coherente con el dashboard y límite de 200 fichas detalladas. | `ContingencyReportController.php`, servicios y vista PDF |
| Detalle | Se retiraron coordenadas crudas que no aportaban una referencia comprensible. | `Contingencies/Show.tsx` |

## Observaciones ya resueltas antes de esta revisión

- El dashboard pagina diez contingencias y conserva filtros al avanzar.
- El dashboard y los informes disponen de limpieza de filtros.
- La actualización de contraseña muestra confirmación de guardado.
- El mapa calcula tamaños de marcador según la cantidad de eventos agrupados.

## Pendientes externos o sujetos a decisión

| Pendiente | Motivo | Acción requerida |
| --- | --- | --- |
| HTTPS en Parra | Requiere dominio, certificado y proxy inverso institucional. | Coordinar con la Universidad; al habilitarlo, activar cookies seguras. |
| Mapa base dentro del PDF | El informe se genera sin dependencias remotas y actualmente usa una referencia esquemática. | Definir una fuente cartográfica estática autorizada o mantener el esquema con su limitación declarada. |
| Windy en el informe de título | La integración ya está aislada y documentada técnicamente. | Incorporar finalidad, dependencia externa y degradación controlada en el capítulo técnico. |
| Revisión visual final de PDF | Debe comprobar orientación, saltos de página y legibilidad con datos representativos. | Renderizar los tres tipos de informe y revisar todas sus páginas antes del despliegue. |

## Verificación automatizada

Ejecutar antes del commit:

```powershell
php artisan test
npm run build
git diff --check
```

Después del despliegue se debe repetir el recorrido manual por rol, sin exponer
credenciales ni datos operacionales en capturas o evidencias.

Resultado local del 4 de noviembre de 2026: Pint aprobado, 167 pruebas con
1.957 aserciones aprobadas, compilación TypeScript/Vite aprobada y
`git diff --check` sin errores. Estos resultados aún no equivalen a una
verificación manual de la nueva imagen en staging.
