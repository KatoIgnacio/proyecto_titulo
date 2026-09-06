# =============================================================
#  CIOP Central - Proyecto de Título
#  Sistema de apoyo a la gestión de contingencias - Luzparral
#
#  Imagen basada en PHP 8.2 + Apache, la misma versión de PHP
#  que corre hoy en XAMPP, para que no aparezcan diferencias
#  de comportamiento entre un entorno y otro.
# =============================================================
FROM php:8.2-apache

LABEL org.opencontainers.image.title="CIOP Central" \
      org.opencontainers.image.description="Sistema de apoyo a la gestion de contingencias electricas" \
      org.opencontainers.image.authors="Bello - Sepulveda" \
      org.opencontainers.image.version="0.1.0-prototipo"

# --- Módulos de Apache -----------------------------------------------------
# deflate  : comprime el KML de la red (baja de ~116 MB a unos pocos MB)
# expires  : permite cachear los assets estáticos
# headers  : requerido por expires
RUN a2enmod deflate expires headers

# --- Configuración propia --------------------------------------------------
COPY docker/php/ciop.ini      /usr/local/etc/php/conf.d/zz-ciop.ini
COPY docker/apache/ciop.conf  /etc/apache2/sites-available/000-default.conf

# --- Código de la aplicación ----------------------------------------------
# Se copia para que la imagen sea autocontenida. En desarrollo,
# docker-compose monta encima estas mismas carpetas desde el disco.
WORKDIR /var/www/html
COPY public/ /var/www/html/public/
COPY src/    /var/www/html/src/

# Los datos NO se copian: se montan como volumen de solo lectura.
# Ver docker-compose.yml y el README.
RUN mkdir -p /data && chown -R www-data:www-data /var/www/html /data

EXPOSE 80

# Verifica que la API responda, no solo que Apache esté vivo.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r "exit(@file_get_contents('http://localhost/api/data.php?action=meta') ? 0 : 1);"
