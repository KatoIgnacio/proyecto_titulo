# CIOP Central — Dashboard de Contingencias (PHP + IIS)

Este proyecto es una web interna tipo “central de control” 
para monitorear contingencias operacionales (clientes sin suministro, 
críticos, electrodependientes, mapa KML, etc.).  
Está construido como SPA simple (sin frameworks)
usando `fetch/AJAX` y un backend **PHP 8** que expone endpoints JSON.

1) Requisitos y restricciones (CRÍTICO)
Servidor / runtime: 
Windows con IIS + PHP 8 vía FastCGI (no se usa XAMPP en el PC institucional).
Sin depender de Internet: 
idealmente evitar CDNs (si el ambiente bloquea internet, mover librerías a `public/assets/vendor/`).
Fuente de datos en vivo: 
los archivos `.txt/.csv/.kml` deben estar en **disco local** o **share UNC**, NO en el pendrive.
Resiliencia: 
si falta un archivo, la UI NO debe caer; se degradará mostrando estado/alertas.
Estructura segura: 
IIS debe apuntar a `/public` para no exponer `/src`.

2) Estructura del proyecto (en disco)
Recomendado en PC institucional:
C:\CIOP_WEB
public
index.php
assets
app.js
style.css
images
logo.png
api
data.php
kml.php
src
config.php
repo.php
parse.php
helpers.php
Document Root (IIS): `C:\CIOP_WEB\public\`
Código privado: `C:\CIOP_WEB\src\` (NO público)

3) Carpeta de datos “en vivo” (DATA_DIR)
Recomendado:
- Disco local: `C:\CIOP_DATA\CIOP-PO\`  
  o share UNC: `\\SERVIDOR\RUTA\CIOP-PO\`
Archivos esperados dentro de DATA_DIR:
- `CIOP-PO.txt`
- `CIOP-PO_clientes.txt`
- `CIOP-PO_criticos.txt`
- `CIOP-PO_ED.txt`
- `Hist_24horas.csv` (opcional)
- `redes.kml` (para mapa)

4) Configuración del proyecto (DATA_DIR y refresco)
Editar:
- `C:\CIOP_WEB\src\config.php`
Ejemplo (disco local):
```php
'DATA_DIR' => 'C:\\CIOP_DATA\\CIOP-PO',
Ejemplo (UNC):
'DATA_DIR' => '\\\\SERVIDOR\\RUTA\\CIOP-PO',
Otros parámetros típicos:
REFRESH_SEC: segundos de auto-refresh (ticker + vista actual)
STALE_MIN: umbral de “datos desfasados” (minutos)

5) Instalación en Windows (IIS + PHP 8 FastCGI)
5.1 Habilitar IIS + CGI
GUI:
Panel de control → Programas → “Activar o desactivar características de Windows”
Marcar:
Internet Information Services
Web Management Tools (IIS Management Console)
World Wide Web Services
Application Development Features:
CGI 
Common HTTP Features:
Static Content 
Default Document 
PowerShell (admin) (opcional):
dism /online /enable-feature /featurename:IIS-WebServerRole /all
dism /online /enable-feature /featurename:IIS-WebServer /all
dism /online /enable-feature /featurename:IIS-CGI /all
dism /online /enable-feature /featurename:IIS-ManagementConsole /all

5.2 Instalar PHP 8 (ZIP, sin instalador)
Descargar PHP 8.x NTS (Non-Thread-Safe) Windows x64 (ZIP).
Extraer en:
C:\PHP\php8\
Debe existir:
C:\PHP\php8\php-cgi.exe
C:\PHP\php8\php.exe
Crear php.ini:
Copiar php.ini-production → php.ini
Editar C:\PHP\php8\php.ini (mínimo):
date.timezone = America/Santiago
cgi.force_redirect = 0
fastcgi.impersonate = 1
log_errors = On
error_log = C:\PHP\php8\php-error.log
display_errors = Off
Validación rápida:
C:\PHP\php8\php-cgi.exe -v
C:\PHP\php8\php.exe -v
Si falla por DLL missing: instalar Microsoft Visual C++ Redistributable x64 (lo gestiona TI).

5.3 Configurar FastCGI en IIS
Abrir IIS Manager (inetmgr)
En el servidor: FastCGI Settings → Add Application:
Full Path: C:\PHP\php8\php-cgi.exe
En el sitio: Handler Mappings → Add Module Mapping:
Request path: *.php
Module: FastCgiModule
Executable: C:\PHP\php8\php-cgi.exe
Name: PHP8_FastCGI
Aceptar si pide habilitar.

5.4 Crear sitio en IIS apuntando a /public
Copiar proyecto a:
C:\CIOP_WEB\
IIS → Sites → Add Website…
Site name: CIOP_WEB
Physical path: C:\CIOP_WEB\public\
Binding:
Type: http
Port: 8080 (o el que defina TI)
Hostname: (vacío, salvo DNS/hosts)
En el sitio: Default Document
asegurar index.php habilitado/arriba.

6) Permisos (IIS debe leer código y DATA_DIR)
Si el App Pool usa ApplicationPoolIdentity:
Identificar el pool (IIS → Application Pools → sitio CIOP_WEB)
En Windows Explorer → Propiedades → Seguridad → Editar → Agregar:
Usuario:
IIS AppPool\CIOP_WEB
(o IIS AppPool\DefaultAppPool si aplica)
Permisos:
Read & Execute
List folder contents
Read
Aplicar a:
C:\CIOP_WEB\
C:\CIOP_DATA\CIOP-PO\ (o el share UNC)
Si es UNC: TI debe dar permisos en el share para esa identidad o usar un usuario de servicio.

7) URLs de operación y validación
Suponiendo puerto 8080 y sitio en root:
UI (SPA):
http://127.0.0.1:8080/
Meta (diagnóstico principal):
http://127.0.0.1:8080/api/data.php?action=meta
General:
http://127.0.0.1:8080/api/data.php?action=general
KML:
http://127.0.0.1:8080/api/kml.php
Checklist rápido
action=meta debe mostrar exists:true y tamaños/mtime para PO/CLIENTES/CRITICOS/ED/KML.
Si meta sale “NO EXISTE” o todo en 0 → DATA_DIR o permisos están mal.

8) Endpoints disponibles (backend)
GET /api/data.php?action=meta
GET /api/data.php?action=general
GET /api/data.php?action=comunas
GET /api/data.php?action=criticos
GET /api/data.php?action=ed
GET /api/data.php?action=map
GET /api/data.php?action=ticker
GET /api/kml.php (sirve redes.kml)

9) Auto-refresh / “tiempo real”
REFRESH_SEC se define en src/config.php.
El frontend refresca:
ticker siempre
la vista actual cada N segundos
Para datos “en vivo”, basta con que el proceso institucional actualice los TXT/CSV/KML en DATA_DIR.

10) Sin Internet (CDN bloqueado)
Si el ambiente bloquea Internet, el mapa/gráficos pueden no cargar si estaban por CDN.
Recomendación
Crear: public/assets/vendor/
Copiar librerías ahí y actualizar referencias en app.js:
Chart.js
Leaflet.js + leaflet.css
Leaflet-omnivore
Esto se realiza como “paquete offline” para despliegue institucional.

11) Dónde modificar si el jefe quiere cambios
UI estilos: public/assets/style.css
Lógica SPA / vistas / refresh: public/assets/app.js
Ruta de datos + refresco: src/config.php
Reglas de negocio (afectados/NIS/comunas): src/helpers.php
Parsing/caché/lectura defensiva: src/repo.php y src/parse.php
Endpoints: public/api/data.php y public/api/kml.php

12) Problemas comunes y solución
Todo en 0 / meta “NO EXISTE”
DATA_DIR incorrecto en src/config.php
Archivos no están en DATA_DIR
Permisos insuficientes a C:\CIOP_DATA\... o share UNC
500/errores PHP
Handler mapping PHP no configurado (FastCGI)
CGI no habilitado
PHP runtime (Visual C++) faltante
Revisar C:\PHP\php8\php-error.log
Mapa o gráficos en blanco
CDN bloqueado → pasar librerías a public/assets/vendor/

13) Nota de seguridad
Mantener src/ fuera del document root. IIS debe apuntar a public/.
No exponer config.php ni repositorios al público.