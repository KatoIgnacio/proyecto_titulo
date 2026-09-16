# Trazabilidad de contingencias

Este documento describe el cierre del RF07 y del RNF06 mediante un flujo de
seguimiento controlado. El registro se prueba exclusivamente con contingencias
y usuarios sintéticos en el entorno académico.

## Secuencia de estados

La secuencia admitida es:

```text
Reportada -> Asignada -> En atención -> Repuesta -> Cerrada
```

Cada transición se define en `App\Enums\ContingencyStatus`. Un cambio que
omite una etapa o intenta modificar una contingencia cerrada es rechazado. La
lógica se mantiene fuera de la interfaz para que pueda reutilizarse desde un
futuro importador, API o proceso autorizado sin duplicar reglas.

## Registro atómico

`TransitionContingencyStatus` ejecuta en una sola transacción:

1. bloqueo del registro de la contingencia;
2. comprobación del estado que observó el usuario;
3. validación de la transición;
4. actualización del estado actual;
5. incorporación de un evento en `contingency_history`.

Si otra sesión actualizó el expediente mientras el formulario permanecía
abierto, la operación se rechaza y solicita recargar la vista. El cambio y la
bitácora se confirman juntos o se revierten juntos.

Al pasar a `restored`, el sistema fija `restored_at` solamente si aún no existe.
El cierre posterior conserva esa hora de reposición.

## Autoría y permisos

- Administración, Supervisión y Operación pueden registrar transiciones.
- Consulta puede visualizar el expediente y su bitácora, pero no modificarlo.
- La restricción se aplica en middleware, en la solicitud validada y en la
  interfaz; ocultar el formulario no constituye el control de seguridad.

Cada evento manual conserva el usuario autenticado, fecha, hora, estado,
antecedente y origen. La aplicación no expone rutas para editar o eliminar
eventos históricos.

Los reportes de terreno agregados mediante RF06 también incorporan un evento
manual en esta bitácora. Su contenido estructurado y sus evidencias se conservan
en tablas separadas, según
[`ANTECEDENTES_TERRENO.md`](ANTECEDENTES_TERRENO.md).

## Verificación

Las pruebas de `ContingencyStatusTransitionTest` comprueban:

- roles autorizados y perfil de solo lectura;
- secuencia válida y rechazo de saltos;
- protección ante actualizaciones simultáneas;
- registro de autoría y origen;
- hora de reposición y cierre posterior;
- transición disponible presentada por el expediente.

Para cerrar el segmento se ejecutan:

```powershell
php artisan test
npm run build
vendor\bin\pint --test
composer validate --strict
```
