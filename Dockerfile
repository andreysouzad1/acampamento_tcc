FROM php:8.2-apache

# Força a desativação de qualquer MPM conflitante e ativa o prefork obrigatoriamente
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
    && rm -f /etc/apache2/mods-enabled/mpm_event.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork

# Habilita extensões essenciais do MySQL para o PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copia os arquivos do projeto para o diretório padrão do Apache no container
COPY . /var/www/html/

# Configura permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80