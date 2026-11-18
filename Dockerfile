FROM php:8.2-apache

# Remove todos os MPMs e força o carregamento apenas do prefork via configuração limpa
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    && rm -f /etc/apache2/mods-enabled/mpm_*.conf \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/ \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/

# Habilita extensões essenciais do MySQL para o PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copia os arquivos do projeto para o diretório padrão do Apache no container
COPY . /var/www/html/

# Configura permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80