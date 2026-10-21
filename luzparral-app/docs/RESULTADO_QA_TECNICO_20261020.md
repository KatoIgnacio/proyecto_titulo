# Resultado de QA técnico del 20 de octubre de 2026

## Alcance

Una revisión exploratoria breve del staging identificó problemas de usabilidad
en acceso, cuentas temporales, descarga de informes y perfil. Esta actividad es
QA técnico informal: no reemplaza el protocolo de validación de usuarios, no
demuestra RNF05 y no debe contabilizarse como resultado de participantes.

Las capturas originales contienen credenciales temporales y no deben
incorporarse al informe ni compartirse sin edición. Este documento conserva
solo la descripción de los hallazgos.

## Hallazgos atendidos

| Área | Hallazgo | Corrección y criterio de aceptación |
| --- | --- | --- |
| Acceso | El primer envío podía no considerar una contraseña completada por el navegador. | El envío obtiene los valores efectivos del formulario, muestra `Ingresando…` y bloquea reenvíos mientras procesa. |
| Mensajes | Se mostraban claves técnicas como `auth.failed` o `validation.min.string`. | Se incorporaron traducciones de autenticación, contraseña y validación al español. |
| Cuentas temporales | `Identificador visible` podía interpretarse como nombre de usuario. | Se renombró a `Nombre visible` y se aclara que el acceso utiliza el correo generado. |
| Listado | Se reportó que una cuenta temporal no aparecía. | Se verificó en backend que el listado incluye cuentas creadas por Administración y por autorregistro, y se agregó una prueba de regresión. |
| Portapapeles | `Copiar credenciales` podía fallar en HTTP o cuando el navegador rechazaba la API moderna. | Se agregó copia alternativa y un mensaje explícito de éxito o de selección manual. |
| Informes | La descarga CSV cambiaba la ubicación de la pestaña. | El archivo se obtiene como `Blob` y se descarga manteniendo la página y sus filtros. |
| Perfil | Parte de la interfaz permanecía en inglés. | Títulos, campos, ayuda y confirmaciones quedaron en español. |

## Verificación requerida en staging

Después de desplegar la imagen que contenga estas correcciones se debe repetir:

1. acceso con una cuenta temporal usando autocompletado y un solo clic;
2. error de acceso y contraseña débil, ambos con mensajes en español;
3. creación administrativa, aparición en el listado y copia de credenciales;
4. autorregistro y aparición posterior en el listado de Administración;
5. descarga CSV sin abandonar Informes ni perder filtros;
6. revisión completa de Perfil en español;
7. confirmación de que Consulta conserva sus restricciones de autorización.

Los resultados de esta repetición deben registrarse por separado y sin exponer
contraseñas, correos personales ni otros datos sensibles.
