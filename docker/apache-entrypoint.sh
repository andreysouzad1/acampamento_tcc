#!/bin/sh
set -e

port="${PORT:-80}"

sed -ri "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:80>#<VirtualHost *:${port}>#" /etc/apache2/sites-enabled/000-default.conf

exec apache2-foreground
