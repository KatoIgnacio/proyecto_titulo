# CIOP Central — entorno Docker

Levanta el prototipo en un contenedor con PHP 8.2 y Apache, la misma versión de PHP
que corre hoy en XAMPP. Sirve para que ambos integrantes trabajen sobre el mismo
entorno y para documentar el ambiente de ingeniería de software en el informe.

Convive con XAMPP: el contenedor usa el puerto **8080** y XAMPP sigue en el **80**.

---

## Dónde va cada archivo

Todo se copia dentro de `CIOP_WEB`, quedando así:

```
TESIS/xampp/htdocs/
├── CIOP_DATA/                  ← los datos, NO se tocan
└── CIOP_WEB/
    ├── Dockerfile              ← nuevo
    ├── docker-compose.yml      ← nuevo
    ├── .dockerignore           ← nuevo
    ├── .gitignore              ← nuevo
    ├── DOCKER.md               ← este archivo
    ├── docker/
    │   ├── apache/ciop.conf    ← nuevo
    │   └── php/ciop.ini        ← nuevo
    ├── public/                 ← ya existe
    └── src/
        └── config.php          ← REEMPLAZAR por la versión nueva
```

El `config.php` nuevo lee la ruta de datos desde una variable de entorno y, si no
la encuentra, usa la ruta de Windows. Por eso sigue funcionando igual en XAMPP.

---

## Requisito previo

Docker Desktop para Windows, que a su vez necesita WSL 2 activado. Si nunca lo
instalaste, la instalación pide reiniciar el equipo una o dos veces.

Para comprobar que quedó bien:

```
docker --version
docker compose version
```

---

## Uso

Desde una terminal parada en `CIOP_WEB`:

```bash
# Construir y levantar
docker compose up -d --build

# Ver qué está pasando (útil si algo falla)
docker compose logs -f

# Detener
docker compose down
```

Luego:

- Aplicación: <http://localhost:8080/>
- Diagnóstico: <http://localhost:8080/api/data.php?action=meta>

Igual que antes, empieza por el diagnóstico: si los seis archivos salen con
`"exists": true`, el contenedor está leyendo bien los datos.

---

## Qué hace distinto respecto de XAMPP

**Comprime el KML.** El archivo de la red pesa 116 MB sin comprimir. En el
contenedor va activado `mod_deflate`, así que viaja comprimido y la vista Mapa
carga bastante más rápido. En tu XAMPP ese módulo está desactivado.

**Zona horaria correcta.** Sin el ajuste, las fechas de modificación se muestran
en UTC y quedan corridas respecto de la hora de Chile.

**Bloquea `src/`.** El VirtualHost niega el acceso por HTTP a la carpeta de
lógica y configuración, además de tenerla fuera de la raíz web.

**No imprime avisos.** `display_errors` va en `Off`, para que ningún aviso de PHP
se cuele antes del JSON y rompa la respuesta de la API. Los errores quedan en
`docker compose logs`.

---

## Los datos nunca entran a la imagen

Es la decisión de diseño más importante de este montaje.

`CIOP_DATA` se monta como volumen **de solo lectura** (`:ro`). No se copia dentro
de la imagen. El `.dockerignore` lo bloquea explícitamente, igual que cualquier
`.txt`, `.csv` o `.kml`.

El motivo es concreto: esa carpeta contiene nombres, direcciones y datos de
clientes críticos y electrodependientes reales. Una imagen Docker se comparte, se
sube a un registro y se copia entre equipos. Si los datos estuvieran adentro, cada
copia arrastraría información que el cliente prohibió exhibir.

Por lo mismo, el `.gitignore` incluido impide versionar esos archivos.

---

## Para una imagen de entrega

Lo anterior es la configuración de desarrollo, donde el código se monta desde el
disco para poder editarlo en vivo. Si necesitas una imagen autocontenida —para
llevarla a otro PC o para adjuntarla como evidencia—, comenta estas dos líneas
del `docker-compose.yml`:

```yaml
      # - ./public:/var/www/html/public
      # - ./src:/var/www/html/src
```

Y reconstruye:

```bash
docker compose up -d --build
```

El código queda dentro de la imagen. Los datos siguen montándose por fuera, que es
como debe ser.

---

## Si algo falla

**El puerto 8080 está ocupado.** Cambia `"8080:80"` por `"8081:80"` en el
`docker-compose.yml`.

**Todas las vistas salen en cero.** El volumen de datos no está llegando. Revisa
que `CIOP_DATA` esté efectivamente al lado de `CIOP_WEB`; si la moviste, corrige
la ruta `../CIOP_DATA` por la ruta absoluta con barras inclinadas hacia adelante.

**La primera construcción demora.** Es normal: baja la imagen base de PHP, unos
150 MB. Las siguientes son casi instantáneas.

**El mapa va más lento que en XAMPP.** Los volúmenes montados desde Windows hacia
el contenedor son lentos para archivos grandes. Si molesta mucho, la alternativa
es copiar `CIOP_DATA` dentro de WSL. Para el uso diario no debería ser un problema.

---

## Lo que este montaje NO resuelve

El cliente tiene WAMP sobre Windows en el PC de la oficina y no va a instalar
Docker ahí. Este entorno sirve para desarrollar de forma consistente entre los dos
integrantes y para documentar el ambiente en el informe, pero la pregunta de cómo
se despliega finalmente en Luzparral sigue abierta y hay que resolverla aparte.
