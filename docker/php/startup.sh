#!/bin/bash

# Создаем структуру директорий для загрузки файлов
mkdir -p /var/www/html/public/profile-images/pet-photos
mkdir -p /var/www/html/public/profile-images/avatars
mkdir -p /var/www/html/public/profile-images/covers

# Устанавливаем правильного владельца и права доступа
chown -R www-data:www-data /var/www/html/public/profile-images/
chmod -R 777 /var/www/html/public/profile-images/

# Запускаем PHP-FPM
exec "$@"