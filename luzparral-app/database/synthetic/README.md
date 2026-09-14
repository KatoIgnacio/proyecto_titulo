# Datos sintéticos Luzparral

Este directorio contiene un esquema MySQL inicial y un generador determinístico
para desarrollo, pruebas y demostración académica. No utiliza nombres,
direcciones, teléfonos, identificadores ni coordenadas de clientes reales.

## Contenido generado por defecto

- 5 usuarios de prueba y cuatro perfiles de acceso.
- 5 comunas públicas y 10 alimentadores ficticios.
- 5.000 puntos de suministro completamente sintéticos.
- 18 lotes de importación con errores controlados para probar RF01.
- 360 contingencias distribuidas en doce meses.
- Afectaciones, tiempos de reposición e historial cronológico.

Los códigos comienzan con `SYN-` y los correos usan el dominio reservado
`example.invalid`, lo que permite reconocer el conjunto como ficticio.

## Ejecución local

Sobre una base vacía, ejecute primero `php artisan migrate` sin seeders. Luego
defina la contraseña fuera del código y ejecute:

```powershell
$env:LUZPARRAL_DB_PASSWORD = '<contraseña-local>'
php database/generate_synthetic.php
Remove-Item Env:LUZPARRAL_DB_PASSWORD
```

Para regenerar exclusivamente un conjunto previamente marcado como sintético:

```powershell
$env:LUZPARRAL_DB_PASSWORD = '<contraseña-local>'
php database/generate_synthetic.php --reset
Remove-Item Env:LUZPARRAL_DB_PASSWORD
```

El generador se niega a operar sobre una base distinta de la indicada en
`LUZPARRAL_DB_ALLOWED_DATABASE` —cuyo valor seguro por defecto es `luzparral`—
y no elimina datos si encuentra contenido que no tenga su marcador sintético.

El archivo `validate_synthetic.sql` contiene los controles de cantidad,
consistencia y carácter sintético usados después de cada generación.
La validación automatizada que retorna un código de error ante inconsistencias es:

```powershell
php artisan luzparral:validate-synthetic --require-runtime
```

El ciclo completo de inicialización, adopción, respaldo y restauración está en
[`docs/CICLO_BASE_DATOS.md`](../../docs/CICLO_BASE_DATOS.md).

## Usuarios de demostración

Todos usan temporalmente la contraseña `LuzparralDemo2026!`. Esta contraseña es
solo para desarrollo y debe reemplazarse antes de cualquier validación externa.

## Base institucional

El mismo generador puede apuntar a la cuenta institucional mediante las
variables `LUZPARRAL_DB_HOST`, `LUZPARRAL_DB_PORT`, `LUZPARRAL_DB_DATABASE`,
`LUZPARRAL_DB_ALLOWED_DATABASE`, `LUZPARRAL_DB_USERNAME` y
`LUZPARRAL_DB_PASSWORD`. El nombre permitido debe declararse explícitamente si
la universidad asigna un esquema distinto. Las credenciales nunca deben
guardarse en Git.
