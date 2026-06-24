FROM wordpress:7-php8.3-apache

# Asennetaan PDO MySQL -laajennus
RUN docker-php-ext-install pdo_mysql

# Nostetaan PHP:n tiedostojen latausrajat paikallista kehitystä varten
# (WordPressin mediakirjaston oletuskatto tulee PHP:stä, oletuksena 2 MB).
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
