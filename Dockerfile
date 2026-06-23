FROM wordpress:7-php8.3-apache

# Asennetaan PDO MySQL -laajennus
RUN docker-php-ext-install pdo_mysql
