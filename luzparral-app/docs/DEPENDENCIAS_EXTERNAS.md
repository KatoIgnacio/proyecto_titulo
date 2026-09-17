# Dependencias externas

Este inventario separa lo necesario para operar el prototipo de lo requerido
solo durante la construcción y de las fuentes que permanecen fuera de alcance.

## Dependencias de ejecución

### MySQL

La aplicación requiere un servidor MySQL accesible mediante las variables
`DB_*`. MySQL no forma parte de la imagen OCI. Si la conexión falla, las vistas
que necesitan datos responden con un error temporal controlado; `/up` permanece
disponible para diagnosticar el contenedor.

### Cartografía de OpenStreetMap

El navegador solicita las teselas del mapa a
`https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` mediante HTTPS. No se
utiliza una clave API. La atribución a OpenStreetMap debe permanecer visible.

Si el servidor de teselas o la conexión a Internet no están disponibles, se
degrada únicamente el fondo cartográfico: los marcadores y los módulos de
dashboard, filtros, búsqueda, detalle e informes continúan consultando MySQL.
El prototipo no precarga ni descarga teselas de forma masiva. Las operaciones
ordinarias de SIGCEL se han validado localmente hasta 30 usuarios concurrentes;
ese resultado no constituye una autorización para generar tráfico masivo hacia
el servidor público de teselas.

Los logotipos, estilos, JavaScript y tipografías de la interfaz se sirven desde
la propia aplicación. No dependen de una CDN en tiempo de ejecución.

### Pronóstico meteorológico de Windy

La vista `Pronóstico meteorológico` incorpora el mapa público de
`https://embed.windy.com` en un marco aislado. Conserva la función disponible
en la beta CIOP: presenta viento superficial con el modelo ECMWF y permite
consultar lluvia, temperatura y presión para la zona de Parral. No utiliza una
clave API y Luzparral no incorpora datos de contingencias ni clientes en la
URL. Como en cualquier recurso web de terceros, el navegador establece una
conexión HTTPS directa con Windy, que puede recibir datos técnicos como la
dirección IP y el agente de usuario conforme a sus propias políticas.

La política de seguridad permite marcos únicamente desde el origen exacto
`https://embed.windy.com`. Si el servicio externo o Internet no están
disponibles, se degrada solo esta vista; dashboard, mapa de contingencias,
búsqueda, detalle e informes continúan funcionando con MySQL. Su carga en el
navegador no forma parte de las mediciones de respuesta del servidor Luzparral.

## Dependencias de construcción

PHP/Composer, Node.js/npm y los repositorios de paquetes se requieren en el
equipo local al construir la imagen. Al transferir a Parra una imagen OCI ya
construida, el servidor institucional no necesita descargar esas dependencias.

## Fuentes y datos fuera de ejecución

- La base de demostración se genera con el conjunto sintético
  `luzparral-synthetic-v1` y no contiene información real de clientes ni de
  personas electrodependientes.
- `CIOP_DATA` y `CIOP_WEB` son antecedentes históricos y de referencia. No se
  copian a la imagen ni se cargan automáticamente durante el arranque.
- La carga desde terreno, sincronización automática con sistemas externos y
  control directo de la red eléctrica permanecen fuera del alcance de esta
  versión.
- Los informes se construyen con los registros persistidos en MySQL y no
  consultan servicios externos.
