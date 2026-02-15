#!/bin/sh
set -e

# Inicia PHP-FPM em background
php-fpm -D

# Nginx em primeiro plano (processo principal do container)
exec nginx -g 'daemon off;'
