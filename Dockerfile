FROM php:8.2-apache

# Habilita extensões essenciais do MySQL para o PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copia os arquivos do projeto para o diretório padrão do Apache no container
COPY . /var/www/html/

# Configura permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80