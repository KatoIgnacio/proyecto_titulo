# Aceptación funcional de staging por rol

Este documento guía una aceptación técnica interna de SIGCEL Luzparral en el
staging institucional de Parra. Su objetivo es detectar defectos antes de una
demostración o de la evaluación con usuarios. No reemplaza el protocolo de
`VALIDACION_USUARIOS.md`, no mide usabilidad y no acredita OE4 ni RNF05.

## Alcance y resguardo

- Entorno: `staging` en Parra, puerto asignado `8004`.
- Acceso recomendado: túnel SSH hacia `127.0.0.1:28004`.
- Datos: exclusivamente el conjunto sintético `luzparral-synthetic-v1`.
- Imagen de referencia: registrar la etiqueta observada al iniciar la sesión.
- Contraseñas: se entregan por canal privado; nunca se escriben en Git, en el
  CSV de resultados, en capturas ni en este documento.
- Evidencias: pueden contener códigos sintéticos, pero no secretos, correos
  personales ni información operacional real.

La aceptación se detiene si `operational-check.sh` falla, si la base deja de
estar saludable o si aparecen datos que no sean sintéticos.

## Preparación

En PowerShell, mantener abierta una ventana exclusiva para el túnel:

```powershell
ssh -N -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 `
  -o ServerAliveCountMax=3 `
  -i "$env:USERPROFILE\.ssh\parra_ubb_2026" `
  -L 127.0.0.1:28004:127.0.0.1:8004 `
  katobello2101@parra.chillan.ubiobio.cl
```

En una segunda sesión SSH, ejecutar antes del recorrido:

```bash
cd ~/luzparral
scripts/operational-check.sh staging
scripts/backup-database.sh staging
podman container inspect luzparral-staging \
  --format 'Imagen={{.ImageName}} Estado={{.State.Status}} Salud={{.State.Health.Status}}'
```

Registrar la imagen, el respaldo creado y la fecha. Luego abrir
`http://127.0.0.1:28004/login`. Si falla la preparación, no continuar con las
pruebas mutables.

## Cuentas sintéticas

| Código | Nombre visible | Correo sintético | Rol |
|---|---|---|---|
| A01 | Administración Demo | `admin@luzparral.example.invalid` | Administración |
| S01 | Supervisión Demo | `supervisor@luzparral.example.invalid` | Supervisión |
| O01 | Operación Turno A | `operador.a@luzparral.example.invalid` | Operación |
| O02 | Operación Turno B | `operador.b@luzparral.example.invalid` | Operación |
| C01 | Consulta Demo | `consulta@luzparral.example.invalid` | Consulta |

Las cinco cuentas usan la clave de demostración entregada por canal privado.
El segundo operador comprueba que la trazabilidad distingue acciones entre
turnos; no representa un rol adicional.

## Matriz esperada de permisos

| Capacidad | A01 | S01 | O01/O02 | C01 |
|---|:---:|:---:|:---:|:---:|
| Dashboard, mapa, pronóstico, búsqueda y detalle | Sí | Sí | Sí | Sí |
| Ver identificadores sintéticos de cliente y suministro | Sí | Sí | Sí | No |
| Cambiar estado de una contingencia | Sí | Sí | Sí | No |
| Registrar antecedente de terreno | Sí | Sí | Sí | No |
| Editar o eliminar antecedentes de terreno | Sí | No | No | No |
| Ver y exportar informes | Sí | Sí | No | No |
| Previsualizar y confirmar importaciones | Sí | Sí | No | No |

Un enlace oculto en la interfaz no basta como control: al intentar abrir una
ruta no autorizada directamente, el servidor debe responder `403`.

## Casos de aceptación

Registrar una fila por cuenta y caso en
`templates/aceptacion_funcional_staging.csv`. Los estados permitidos son
`OK`, `FALLA` y `NO_EJECUTADO`.

### Casos no mutables

| Código | Cuentas | Acción | Resultado esperado |
|---|---|---|---|
| AF01 | Todas | Iniciar sesión y cerrar sesión | Acceso correcto, nombre y rol visibles; sesión terminada al salir |
| AF02 | Todas | Abrir dashboard y aplicar un rango de fechas | Indicadores y listado responden al mismo filtro |
| AF03 | Todas | Abrir mapa, pronóstico, búsqueda y detalle | Cada módulo carga sin reiniciar el túnel ni perder la sesión |
| AF04 | A01, S01, O01, O02 | Buscar por cliente y suministro sintético | Opciones y resultados autorizados visibles |
| AF05 | C01 | Revisar buscador y mapa | No expone identificadores ni capas sensibles |
| AF06 | A01, S01 | Abrir informes y exportar CSV/PDF | Módulo disponible y archivos descargables |
| AF07 | O01, O02, C01 | Abrir `/informes` directamente | Respuesta `403`; módulo ausente del menú |
| AF08 | A01, S01 | Abrir importaciones y descargar plantilla | Pantalla y plantilla disponibles; no confirmar un lote |
| AF09 | O01, O02, C01 | Abrir `/importaciones` directamente | Respuesta `403`; módulo ausente del menú |

### Casos mutables controlados

Estos casos solo se ejecutan después del respaldo. Usar una contingencia
sintética abierta y anotar su código en `observation`. No reutilizar una
contingencia que se mostrará al comité si el cambio altera el relato previsto.

| Código | Cuenta | Acción | Resultado esperado |
|---|---|---|---|
| AF10 | O01 | Realizar únicamente la siguiente transición válida | Estado e historial muestran a Operación Turno A |
| AF11 | O02 | Registrar antecedente de terreno con texto claramente sintético | Antecedente guardado con autor, fecha y avance correctos |
| AF12 | S01 | Intentar editar o eliminar el antecedente de AF11 | Controles ausentes y ruta directa rechazada con `403` |
| AF13 | A01 | Editar el antecedente de AF11 y luego eliminarlo con confirmación | Cambio permitido; auditoría conserva creación, edición y eliminación |
| AF14 | C01 | Intentar transición y registro mediante ruta directa | Respuesta `403`; no se modifica la contingencia |

No confirmar importaciones durante esta aceptación salvo que exista un caso de
defecto específico y un CSV sintético identificado. La previsualización es
suficiente para el recorrido normal.

## Orden de ejecución

1. Completar AF01-AF09 con A01 y cerrar sesión.
2. Repetir los casos aplicables con S01, O01, O02 y C01, cerrando sesión entre
   cuentas para evitar atribución incorrecta.
3. Ejecutar AF10-AF14 sobre datos sintéticos designados.
4. Capturar solo la pantalla necesaria para respaldar cada falla o hito.
5. Ejecutar nuevamente `scripts/operational-check.sh staging`.
6. Revisar el CSV: cada caso debe tener estado y referencia de evidencia.

## Criterio de cierre

El segmento queda técnicamente aceptado cuando:

- AF01-AF14 están en `OK`, o cualquier `NO_EJECUTADO` tiene justificación;
- no existe una falla de severidad crítica o alta pendiente;
- la matriz visible coincide con la autorización efectiva del backend;
- la aplicación y MySQL terminan saludables;
- el respaldo previo y las evidencias están identificados;
- las contraseñas y datos personales no aparecen en Git.

Una falla crítica es acceso de una cuenta no autorizada, exposición de datos,
pérdida de trazabilidad o indisponibilidad general. Una falla alta impide un
recorrido principal de demostración. Las demás observaciones se priorizan sin
declarar falsamente que la validación con usuarios ya fue realizada.

## Evidencia y limpieza

El CSV de ejecución puede versionarse solo si contiene cuentas sintéticas y
referencias seguras. Las capturas se guardan fuera del repositorio cuando
muestran información de sesión o elementos no destinados a publicación.

Los cambios de AF10-AF13 son evidencia auditable sobre datos sintéticos. No se
restaura la base automáticamente: si se necesita recuperar la línea base para
la presentación, se utiliza el respaldo identificado y el procedimiento de
`CICLO_BASE_DATOS.md`, con una nueva verificación operativa al terminar.
