FROM wordpress:6.8.3-php8.3-apache

# Asennetaan PDO MySQL -laajennus
RUN docker-php-ext-install pdo_mysql
