FROM php:8.2-cli

# Habilita extensões essenciais do MySQL para o PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copia os arquivos do projeto para dentro do container
COPY . /var/www/html/
WORKDIR /var/www/html

# Expõe a porta que o Railway utiliza (porta padrão 8080 ou 80)
EXPOSE 8080

# Inicia o servidor embutido do PHP apontando para todas as interfaces
CMD ["php", "-S", "0.0.0.0:8080"]