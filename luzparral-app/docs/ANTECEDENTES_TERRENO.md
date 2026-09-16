# Antecedentes y reportes de terreno

Este módulo implementa el RF06 mediante registros estructurados vinculados al
expediente de una contingencia. Su propósito es conservar la evolución del
trabajo en terreno sin depender de observaciones sueltas ni de archivos fuera
del sistema.

## Información registrada

Cada antecedente conserva:

- contingencia asociada;
- avance observado: inspección, reparación, espera de recursos o trabajo
  completado;
- descripción operacional de hasta 2.000 caracteres;
- fecha y hora de la observación;
- coordenadas opcionales, ingresadas siempre como par latitud/longitud;
- usuario responsable y fecha de incorporación;
- hasta tres evidencias JPG, PNG o PDF de un máximo de 5 MB cada una.

El registro crea además un evento manual en `contingency_history`. El
antecedente y su entrada de trazabilidad se confirman dentro de la misma
transacción.

## Evidencias y seguridad

Los archivos se almacenan en el disco privado `local`, bajo
`storage/app/private/field-reports`. No se publican mediante enlaces directos ni
se exponen sus rutas internas. La descarga pasa por una ruta autenticada y cada
archivo conserva nombre original, tipo MIME, tamaño y suma SHA-256.

La versión académica utiliza únicamente descripciones, ubicaciones y archivos
sintéticos. No deben incorporarse nombres, teléfonos, direcciones, documentos
ni coordenadas de clientes reales.

## Permisos

- Administración, Supervisión y Operación pueden registrar antecedentes.
- Consulta puede revisar antecedentes y descargar sus evidencias, pero no
  crearlos.
- No existen rutas para editar o eliminar reportes históricos desde la
  interfaz.

La autorización se aplica en middleware, en la solicitud validada y en la
interfaz.

## Verificación

`FieldReportTest` comprueba autenticación, roles, validación temporal y
geográfica, creación conjunta de trazabilidad, carga privada, restricciones de
tipo de archivo, descarga autenticada y ausencia de rutas privadas en las
propiedades entregadas al navegador.

Para cerrar el segmento se ejecutan:

```powershell
php artisan test
npm run build
vendor\bin\pint --test
composer validate --strict
```
