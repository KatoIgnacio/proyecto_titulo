# Importación controlada de contingencias

El módulo implementa el ingreso trazable de contingencias mediante archivos
sintéticos. No consume actualmente Power On, CIOP ni archivos operacionales
reales: la primera fuente habilitada es `CSV sintético SIGCEL v1`.

## Acceso y flujo

Solo los perfiles Administración y Supervisión pueden abrir el módulo o invocar
sus rutas. El procedimiento consta de dos acciones separadas:

1. descargar la plantilla, completar el archivo y validarlo;
2. revisar las filas aceptadas y rechazadas y confirmar la importación.

La validación previa no escribe en la base. Al confirmar, el servidor vuelve a
leer el archivo y compara su SHA-256 con el resultado validado. Si cambió, exige
una nueva validación. El archivo original no se conserva en disco.

## Formato `synthetic_csv_v1`

Se admiten archivos `.csv` o `.txt` de hasta 2 MB y 1.000 filas, codificados en
UTF-8 o Windows-1252. El separador se detecta entre punto y coma, coma y
tabulación. La cabecera obligatoria es:

```text
codigo;osf;comuna;alimentador;estado;prioridad;causa;descripcion;inicio;reposicion_estimada;reposicion_efectiva;latitud;longitud
```

Reglas principales:

- `codigo` y `osf` deben ser únicos, comenzar con `SYN-` y usar letras,
  números o guiones;
- `comuna` y `alimentador` deben existir, estar activos y corresponder entre sí;
- `estado`: `reported`, `assigned`, `in_progress`, `restored` o `closed`;
- `prioridad`: `critical`, `high`, `medium` o `low`;
- `causa`: `weather`, `vegetation`, `equipment_failure`,
  `vehicle_collision`, `third_party` o `unknown`;
- las fechas usan `AAAA-MM-DD HH:MM:SS` o una variante ISO equivalente;
- `restored` y `closed` requieren fecha de reposición efectiva;
- las coordenadas se restringen al área sintética configurada para este
  prototipo y la descripción debe contener entre 10 y 300 caracteres.

La plantilla descargable contiene una fila ficticia de ejemplo. Antes de
importarla se debe usar un código distinto de los ya existentes.

## Resultado y trazabilidad

Un lote puede quedar `Completada`, si todas las filas se incorporaron; `Con
observaciones`, si se incorporaron las filas válidas y se descartaron otras; o
`Rechazada`, si no se incorporó ninguna fila. Las filas válidas generan la
contingencia y su primer evento de historial; las inválidas se registran en
`import_errors` con fila, campo, código de error y referencia sintética. La
interfaz permite revisar los diez lotes más recientes y hasta las primeras cien
observaciones del lote seleccionado.

Los lotes sintéticos precargados incluyen anomalías deliberadas, como duraciones
negativas, coordenadas ausentes, estados inválidos y problemas de codificación.
Sirven para demostrar la validación y no reproducen errores ni registros reales
de documentos de la empresa.

El lote, sus errores, las contingencias aceptadas y su historial se escriben en
una sola transacción. Un fallo inesperado revierte todo el lote. Los índices
únicos de código y OSF constituyen una segunda barrera contra duplicados; si
otra importación gana una carrera concurrente, se solicita volver a validar.

## Extensión futura

La lectura está separada mediante `ContingencyImportAdapter`. Una futura fuente
Power On o CIOP deberá implementar ese contrato y devolver el mismo resultado
normalizado, sin duplicar la transacción, la auditoría ni las reglas de acceso.
Su habilitación exige primero confirmar el formato real, el canal de entrega,
la clasificación de datos y las reglas de conciliación con la empresa.

## Verificación local

```powershell
php artisan test --filter=ContingencyImportTest
npm run build
```

Las pruebas cubren autenticación y roles, previsualización sin escrituras,
archivos válidos y mixtos, duplicados, integridad por checksum, Windows-1252,
cabeceras inválidas, descarga de plantilla y reversión transaccional.
