# Calidad y rendimiento local

Este documento registra el cierre local del Segmento 7. Las mediciones se
realizan con el conjunto sintético controlado y no reemplazan la verificación
posterior en el servidor Parra.

## Alcance validado

- Filtros rápidos y selección calendario por día, mes, año o rango
  personalizado (`Desde` y `Hasta`).
- Aplicación consistente del período en dashboard, mapa, informes, CSV y PDF.
- Validación de fechas obligatorias, formato y orden cronológico.
- Recuperación de la vista meteorológica Windy de la beta CIOP, aislada como
  dependencia externa y protegida por CSP.
- Suite automatizada, compilación del frontend y ejecución integral en Docker.
- Carga de 5 y 10 sesiones autenticadas concurrentes.

## Procedimiento reproducible

Desde PowerShell 7, con Docker Desktop iniciado:

```powershell
pwsh -NoProfile -File .\deploy\VALIDAR_INTEGRACION_LOCAL.ps1 -RunPerformance
```

El procedimiento crea una base MySQL aislada, genera únicamente datos
sintéticos, valida el esquema, prueba los módulos y ejecuta
[`MEDIR_RENDIMIENTO_LOCAL.ps1`](../deploy/MEDIR_RENDIMIENTO_LOCAL.ps1). La
contraseña temporal se transmite por variable de entorno, no se imprime ni se
almacena. Al finalizar correctamente se eliminan los contenedores y el volumen
de prueba.

La medición usa dos solicitudes por sesión y escenario, después de una
solicitud de calentamiento no contabilizada. El criterio exige cero errores
HTTP y que el percentil 95 no supere el límite de cada operación.

## Entorno medido

- Fecha: 15 de septiembre de 2026.
- Equipo: Intel Core i7-10750H, 15,8 GB RAM.
- Sistema anfitrión: Windows 10 Home Single Language.
- Docker Engine 29.7.2 y PowerShell 7.6.5.
- Aplicación Linux/AMD64 con PHP 8.3 y Apache.
- MySQL 8.4.9 aislado.
- Datos: 360 contingencias, 5.000 suministros, 25.283 impactos y 1.585 eventos
  de historial, todos sintéticos.

## Resultados

| Sesiones | Operación | Solicitudes | Fallos | Promedio | P95 | Límite P95 |
|---:|---|---:|---:|---:|---:|---:|
| 5 | Dashboard | 10 | 0 | 68,88 ms | 80,94 ms | 2.000 ms |
| 5 | Mapa | 10 | 0 | 37,36 ms | 45,54 ms | 2.500 ms |
| 5 | Pronóstico | 10 | 0 | 26,20 ms | 37,62 ms | 1.000 ms |
| 5 | Informes | 10 | 0 | 56,46 ms | 59,16 ms | 3.000 ms |
| 5 | CSV | 10 | 0 | 77,38 ms | 97,09 ms | 5.000 ms |
| 5 | PDF ejecutivo | 10 | 0 | 3.679,61 ms | 3.894,73 ms | 12.000 ms |
| 10 | Dashboard | 20 | 0 | 71,31 ms | 88,05 ms | 2.000 ms |
| 10 | Mapa | 20 | 0 | 42,46 ms | 55,98 ms | 2.500 ms |
| 10 | Pronóstico | 20 | 0 | 21,28 ms | 24,61 ms | 1.000 ms |
| 10 | Informes | 20 | 0 | 66,31 ms | 72,87 ms | 3.000 ms |
| 10 | CSV | 20 | 0 | 118,37 ms | 133,73 ms | 5.000 ms |
| 10 | PDF ejecutivo | 20 | 0 | 5.611,18 ms | 5.747,19 ms | 12.000 ms |

Resultado: 180 solicitudes medidas, cero fallos y todos los criterios locales
aprobados.

## Interpretación y límite conocido

Dashboard, mapa, listado de informes y CSV mantienen tiempos bajos con el
máximo previsto de diez sesiones. La generación simultánea de PDF es la tarea
más costosa; permanece dentro del límite local, pero debe volver a medirse en
Parra. Si en el futuro aumenta el volumen o la concurrencia, convendrá mover la
generación de PDF a una cola asíncrona.

La prueba HTTP mide la respuesta producida por Luzparral. No incorpora el tiempo
de descarga de teselas de OpenStreetMap ni del mapa Windy, porque esos recursos
proceden de servicios externos. La ruta interna que entrega la vista de
pronóstico sí se incluye en la medición reformulada del segmento.

La revisión visual confirmó la carga efectiva del marco Windy, su línea
temporal meteorológica y la selección calendario. Como prueba funcional, el
modo `Día específico` aplicado al 9 de septiembre de 2026 redujo el conjunto a
las dos contingencias sintéticas registradas en esa fecha.

## Comprobaciones finales del segmento

```powershell
php artisan test
npm run build
vendor\bin\pint --test
composer validate --strict
```

El archivo JSON detallado de cada ejecución queda en
`storage/app/quality/segment-7-performance.json` y no se versiona.
