# Búsqueda operacional y mapa escalable

Este módulo completa el soporte local de RF04 y RF05 con datos exclusivamente
sintéticos. Separa la consulta tabular de la visualización geográfica y limita
las respuestas para que el navegador no dependa del tamaño total de la base.

## Búsqueda operacional

La búsqueda de contingencias admite código, OSF, comuna, alimentador,
descripción, causa, estado y criticidad. Los resultados se ordenan de forma
estable por fecha e identificador y se entregan en páginas de 15 registros.

Administración, Supervisión y Operación también pueden consultar el comienzo o
el código completo de un cliente o suministro sintético. Esta búsqueda:

- exige al menos tres caracteres;
- aprovecha los índices únicos de `customer_code` y `synthetic_code`;
- devuelve códigos ficticios, comuna, alimentador, clasificación y cantidad de
  contingencias asociadas;
- nunca devuelve coordenadas desde el buscador;
- rechaza en el backend los intentos del perfil Consulta, aunque se manipule la
  URL o la interfaz.

La categoría general no mezcla inadvertidamente identificadores de suministro
con resultados de contingencias. La ampliación futura a datos reales exige una
revisión de permisos, finalidad y tratamiento antes de habilitar esa fuente.

## Consulta geográfica por área visible

La página carga un resumen inicial y luego consulta
`/contingencias/mapa/datos` cada vez que termina un desplazamiento o cambio de
zoom. El servidor recibe los límites norte, sur, este y oeste, aplica los mismos
filtros temporales y operacionales y devuelve solamente el área visible.

La petición rechaza límites invertidos, zoom fuera de 6 a 18 y áreas mayores a
10 grados. Las consultas utilizan los índices de coordenadas existentes.

## Agrupación y límites

Las contingencias se agrupan en una cuadrícula cuya precisión aumenta con el
zoom. Un círculo representa uno o varios eventos, crece según su cantidad y
conserva los totales agregados de afectados, críticos y electrodependientes. Al
seleccionar un grupo, el mapa se acerca; cuando queda un evento individual se
habilita su expediente.

Cada respuesta entrega como máximo 400 grupos de contingencias y 200 zonas por
capa. El resumen se calcula sobre toda la selección visible, no solamente sobre
los elementos dibujados. Si se alcanza un límite, la interfaz solicita acercar
el mapa en vez de descargar un conjunto ilimitado.

## Capas protegidas

El selector del mapa permite activar o desactivar:

- Contingencias;
- Zonas críticas;
- Zonas electrodependientes.

Las dos últimas capas están disponibles para Administración, Supervisión y
Operación. Cada zona agrupa puntos sintéticos afectados y contingencias, sin
incluir códigos de cliente, códigos de suministro ni ubicaciones individuales.
Las coordenadas se redondean a una cuadrícula aproximada de un kilómetro y no
aumentan su precisión al acercar el mapa.
El perfil Consulta recibe únicamente la capa de contingencias y métricas
agregadas.

Las zonas son referenciales: apoyan el análisis, pero no representan polígonos
eléctricos oficiales ni reemplazan herramientas de operación de red.

## Verificación local

```powershell
php artisan test --filter="(OperationalSearchTest|ContingencyMapTest)"
npm run build
```

Las pruebas cubren paginación, búsqueda protegida, intento de acceso forzado,
filtro por área visible, agrupación por zoom, capas anonimizadas y una respuesta
acotada frente a 450 contingencias visibles.
