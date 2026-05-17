# Utiliser une image officielle PHP avec le serveur web Apache
FROM php:8.2-apache

# Mettre à jour le serveur et installer les extensions PostgreSQL pour PHP (PDO)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copier tout le code de votre projet dans le dossier du serveur web
COPY . /var/www/html/

# Donner les bons droits de lecture au serveur
RUN chown -R www-data:www-data /var/www/html
