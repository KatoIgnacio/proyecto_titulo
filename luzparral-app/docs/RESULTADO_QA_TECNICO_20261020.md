# Resultado de QA técnico del 20 de octubre de 2026

## Alcance

Una revisión exploratoria breve del staging identificó problemas de usabilidad
en acceso, cuentas temporales, descarga de informes y perfil. Esta actividad es
QA técnico informal: no reemplaza el protocolo de validación de usuarios, no
demuestra RNF05 y no debe contabilizarse como resultado de participantes.

Las capturas originales contienen credenciales temporales y no deben
incorporarse al informe ni compartirse sin edición. Este documento conserva
solo la descripción de los hallazgos.

## Estado vigente del flujo de acceso

La interfaz actual conserva un único mecanismo de alta: el autorregistro desde
la pantalla de acceso. Se retiró la creación administrativa de cuentas
temporales, su listado y la copia de credenciales porque duplicaban ese flujo.
Por ello, los hallazgos históricos de `Cuentas temporales`, `Listado` y
`Portapapeles` se mantienen abajo como evidencia, pero ya no aplican a la
interfaz vigente.

## Hallazgos atendidos

| Área | Hallazgo | Corrección y criterio de aceptación |
| --- | --- | --- |
| Acceso | El primer envío podía no considerar una contraseña completada por el navegador. | El envío obtiene los valores efectivos del formulario, muestra `Ingresando…` y bloquea reenvíos mientras procesa. |
| Mensajes | Se mostraban claves técnicas como `auth.failed` o `validation.min.string`. | Se incorporaron traducciones de autenticación, contraseña y validación al español. |
| Cuentas temporales (histórico) | `Identificador visible` podía interpretarse como nombre de usuario. | Retirado de la interfaz vigente junto con la creación administrativa; no aplica al autorregistro público. |
| Listado (histórico) | Se reportó que una cuenta temporal no aparecía. | Retirado de la interfaz vigente; el acceso ya no depende de un listado administrativo paralelo. |
| Portapapeles (histórico) | `Copiar credenciales` podía fallar en HTTP o cuando el navegador rechazaba la API moderna. | Retirado de la interfaz vigente; cada participante define y conserva su propia credencial. |
| Autorregistro | El campo nombre admitía cadenas de símbolos que no representaban un nombre de participante. | Se normalizan los espacios y solo se aceptan letras, espacios, apóstrofes y guiones, con un mensaje de ayuda y una prueba de regresión. |
| Informes | La descarga CSV cambiaba la ubicación de la pestaña. | El archivo se obtiene como `Blob` y se descarga manteniendo la página y sus filtros. |
| Perfil | Parte de la interfaz permanecía en inglés. | Títulos, campos, ayuda y confirmaciones quedaron en español. |

## Verificación requerida en staging

Después de desplegar la imagen que contenga estas correcciones se debe repetir:

1. autorregistro desde la pantalla de acceso con un nombre humano válido;
2. rechazo de un nombre compuesto por símbolos o marcado y de una contraseña
   débil, ambos con mensajes en español;
3. acceso con autocompletado y un solo clic;
4. descarga CSV sin abandonar Informes ni perder filtros;
5. revisión completa de Perfil en español;
6. confirmación de que Consulta conserva sus restricciones de autorización;
7. confirmación de que `/cuentas-consulta` y sus operaciones administrativas
   ya no están disponibles.

Los resultados de esta repetición deben registrarse por separado y sin exponer
contraseñas, correos personales ni otros datos sensibles.
