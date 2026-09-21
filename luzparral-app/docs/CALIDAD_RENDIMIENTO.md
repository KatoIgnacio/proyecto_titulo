# Calidad y rendimiento local

Este documento registra la validación local acumulada hasta el Segmento 19.
Las mediciones usan exclusivamente datos sintéticos y no reemplazan la
verificación posterior en el servidor Parra.

## Alcance validado

- Filtros calendario por día, mes, año, rango personalizado o historial
  completo; el rango personalizado es la selección inicial.
- Aplicación consistente del período en dashboard, mapa, informes, CSV y PDF.
- Dashboard, búsqueda operacional, registro y mantenimiento administrativo de
  antecedentes, mapa, informes, importaciones y pronóstico autenticados.
- Windy aislado como dependencia externa y protegido por CSP.
- Suite automatizada, compilación frontend y ejecución integral en Docker.
- Carga autenticada de 5, 10 y 30 sesiones para operaciones ordinarias.
- Exportaciones intensivas CSV y PDF medidas por separado hasta 10 sesiones.

## Procedimiento reproducible

Desde PowerShell 7, con Docker Desktop iniciado:

```powershell
pwsh -NoProfile -File .\deploy\VALIDAR_INTEGRACION_LOCAL.ps1 -RunPerformance
```

El procedimiento crea una base MySQL aislada, genera solo datos sintéticos,
valida el esquema, prueba los módulos y ejecuta
[`MEDIR_RENDIMIENTO_LOCAL.ps1`](../deploy/MEDIR_RENDIMIENTO_LOCAL.ps1). La
contraseña temporal se transmite por variable de entorno, no se imprime ni se
almacena. Al finalizar correctamente se eliminan contenedores y volumen.

Cada escenario usa dos solicitudes por sesión, después de una solicitud de
calentamiento no contabilizada. El criterio exige cero errores HTTP y que el
percentil 95 no supere el límite del escenario. La cookie CSRF se renueva tras
autenticar para medir correctamente la operación POST de terreno.

## Entorno medido

- Fecha: 17 de septiembre de 2026.
- Equipo: Intel Core i7-10750H, 15,8 GB RAM.
- Sistema anfitrión: Windows 10 Home Single Language.
- Docker Engine 29.7.2 y PowerShell 7.6.5.
- Aplicación Linux/AMD64 con PHP 8.3 y Apache.
- MySQL 8.4.9 aislado.
- Datos: 360 contingencias, 5.000 suministros, 25.283 impactos, 144 reportes de
  terreno y 1.729 eventos de historial, todos sintéticos.

## Resultados

| Sesiones | Operación | Solicitudes | Fallos | Promedio | P95 | Límite P95 |
|---:|---|---:|---:|---:|---:|---:|
| 5 | Dashboard | 10 | 0 | 89,76 ms | 111,52 ms | 3.000 ms |
| 5 | Mapa | 10 | 0 | 77,10 ms | 82,96 ms | 3.000 ms |
| 5 | Búsqueda | 10 | 0 | 37,96 ms | 43,21 ms | 3.000 ms |
| 5 | Registro terreno | 10 | 0 | 98,58 ms | 130,30 ms | 3.000 ms |
| 5 | Pronóstico | 10 | 0 | 27,67 ms | 33,68 ms | 3.000 ms |
| 5 | Informes | 10 | 0 | 66,40 ms | 72,54 ms | 3.000 ms |
| 5 | CSV | 10 | 0 | 119,30 ms | 135,82 ms | 5.000 ms |
| 5 | PDF ejecutivo | 10 | 0 | 4.669,63 ms | 4.914,74 ms | 12.000 ms |
| 10 | Dashboard | 20 | 0 | 79,50 ms | 83,79 ms | 3.000 ms |
| 10 | Mapa | 20 | 0 | 102,59 ms | 118,55 ms | 3.000 ms |
| 10 | Búsqueda | 20 | 0 | 41,08 ms | 52,38 ms | 3.000 ms |
| 10 | Registro terreno | 20 | 0 | 122,48 ms | 143,06 ms | 3.000 ms |
| 10 | Pronóstico | 20 | 0 | 33,66 ms | 46,55 ms | 3.000 ms |
| 10 | Informes | 20 | 0 | 61,87 ms | 66,77 ms | 3.000 ms |
| 10 | CSV | 20 | 0 | 143,20 ms | 161,95 ms | 5.000 ms |
| 10 | PDF ejecutivo | 20 | 0 | 7.070,21 ms | 7.554,37 ms | 12.000 ms |
| 30 | Dashboard | 60 | 0 | 111,57 ms | 156,88 ms | 3.000 ms |
| 30 | Mapa | 60 | 0 | 167,19 ms | 365,47 ms | 3.000 ms |
| 30 | Búsqueda | 60 | 0 | 81,48 ms | 165,43 ms | 3.000 ms |
| 30 | Registro terreno | 60 | 0 | 273,56 ms | 392,45 ms | 3.000 ms |
| 30 | Pronóstico | 60 | 0 | 72,73 ms | 155,48 ms | 3.000 ms |
| 30 | Informes | 60 | 0 | 175,60 ms | 358,78 ms | 3.000 ms |

Resultado: 600 solicitudes medidas, cero fallos y todos los criterios locales
aprobados. En el nivel de 30 sesiones se ejecutaron 360 solicitudes ordinarias;
el peor P95 fue 392,45 ms, por debajo del máximo de 3.000 ms de RNF01.

## Interpretación y límites

RNF01 y RNF03 quedan demostrados para el entorno local: las consultas, filtros
y registros ordinarios mantienen el límite de tres segundos con 30 sesiones.
El resultado debe repetirse en Parra porque el hardware, la red y MySQL serán
distintos.

La generación de PDF es más costosa y se limita a diez usuarios concurrentes
en este ensayo. Permanece dentro de su umbral específico, pero no se usa para
concluir RNF01 o RNF03. Si aumenta la demanda, debe trasladarse a una cola
asíncrona.

La prueba HTTP mide respuestas de SIGCEL. No incorpora la descarga de teselas
de OpenStreetMap ni el contenido embebido de Windy, porque son servicios
externos. La ruta interna del pronóstico sí se incluye.

## Comprobaciones finales

```powershell
php artisan test
npm run build
vendor\bin\pint --test
composer validate --strict
```

El JSON detallado queda en
`storage/app/quality/segment-13-performance.json` y no se versiona.
