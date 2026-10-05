# Resultado de aceptación funcional de staging — 5 de octubre de 2026

## Resultado ejecutivo

La aceptación técnica interna de SIGCEL Luzparral terminó APROBADA. Los casos
AF01 a AF14 resultaron OK, no quedaron defectos críticos o altos abiertos y la
aplicación concluyó saludable. Esta ejecución acredita funcionamiento y
autorización por rol en staging; no reemplaza una evaluación con usuarios.

## Entorno controlado

| Elemento | Evidencia |
|---|---|
| Ejecución | AF-STG-20261005 |
| Servidor | Parra, entorno staging |
| Puerto institucional | 8004, publicado hacia 8080 del contenedor |
| Imagen | luzparral-app:ef209b7d3d9e |
| Datos | luzparral-synthetic-v1, exclusivamente sintéticos |
| Base de datos | MySQL en red privada; 3306 no publicado |
| Operación | Podman rootless, Linger habilitado y reinicio unless-stopped |
| Acceso de prueba | Túnel SSH local 127.0.0.1:28004 hacia 127.0.0.1:8004 |

Antes de las pruebas mutables se ejecutaron operational-check.sh y un respaldo.
El respaldo identificado fue
staging-20261005T230556Z.sql, acompañado por su archivo SHA-256.

La evidencia automatizada previa comprendió 145 pruebas y 1.636 aserciones,
incluyendo autorización, trazabilidad, importaciones y protección frente a
inyección SQL.

## Resultado por cuenta

| Código | Rol | Resultado |
|---|---|---|
| A01 | Administración | OK |
| S01 | Supervisión | OK |
| O01 | Operación Turno A | OK |
| O02 | Operación Turno B | OK |
| C01 | Consulta | OK |

Las cinco cuentas sintéticas iniciaron y cerraron sesión correctamente. La
interfaz visible y las respuestas 403 del backend coincidieron con la matriz de
permisos esperada.

## Resultado por caso

| Caso | Estado | Evidencia resumida |
|---|---|---|
| AF01 | OK | Inicio y cierre de sesión aprobados en las cinco cuentas |
| AF02 | OK | Dashboard y filtros de fechas respondieron de forma consistente |
| AF03 | OK | Mapa, pronóstico, búsqueda y detalle cargaron con sesión activa |
| AF04 | OK | A01, S01, O01 y O02 consultaron códigos sintéticos autorizados |
| AF05 | OK | C01 no recibió identificadores ni capas sensibles |
| AF06 | OK | A01 y S01 accedieron a informes y exportaciones |
| AF07 | OK | O01, O02 y C01 no vieron Informes y la ruta directa fue prohibida |
| AF08 | OK | A01 y S01 accedieron a importaciones y a su plantilla |
| AF09 | OK | O01, O02 y C01 no vieron Importaciones y la ruta fue prohibida |
| AF10 | OK | O01 cambió SYN-CONT-000001 de Asignada a En progreso con autoría visible |
| AF11 | OK | O02 creó un antecedente sintético atribuido a Operación Turno B |
| AF12 | OK | S01 pudo leer el antecedente, sin editarlo ni eliminarlo |
| AF13 | OK | A01 editó y eliminó el antecedente; la auditoría conservó las acciones |
| AF14 | OK | C01 conservó acceso de solo lectura y no obtuvo formularios mutables |

AF10 a AF14 se ejecutaron sobre datos sintéticos después del respaldo. No se
confirmó ninguna importación durante el recorrido.

## Incidencia resuelta

INC-AF-01: durante el primer recorrido, S01 no pudo autenticarse aunque la
cuenta estaba activa. La comprobación segura mostró que la credencial entregada
no coincidía con el hash almacenado. No se expusieron contraseñas ni hashes.

Los mecanismos ordinarios rechazaron correctamente la reactivación de una
credencial sintética y la ejecución del seeder bajo configuración de
producción. Se realizó mantenimiento controlado limitado a las cinco cuentas
example.invalid, se revocaron sus sesiones y se repitieron los accesos. Las
cinco cuentas quedaron verificadas. La causa original de la divergencia no se
determinó y no se atribuye sin evidencia.

## Auditoría posterior

La verificación final informó:

- aplicación OK;
- contenedor running y healthy;
- puerto 8004 hacia 8080;
- MySQL privado y 3306 no publicado;
- encabezados y recuperación web OK;
- conexión y esquema OK;
- auditoría operativa OK;
- persistencia en luzparral-mysql-data y luzparral-staging-storage;
- uso de disco de 3 %;
- puertos no asignados 2003 y 2004 libres;
- respaldo staging-20261005T230556Z.sql verificado y con edad de cero días.

## Alcance académico

Esta ejecución cierra la aceptación funcional técnica de staging y deja una
línea base apta para demostración. OE4 y RNF05 permanecen pendientes de la
evaluación autorizada con participantes reales y del cálculo de su métrica de
éxito, sin inferir resultados desde estas pruebas internas.

El detalle estructurado se conserva en
evidence/aceptacion_funcional_staging-20261005.csv.