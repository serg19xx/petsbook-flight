#!/bin/bash

# Автоматический деплой для PetsBook
set -e

# Конфигурация
SERVER_IP="64.188.10.53"
SERVER_USER="root"
PROJECT_PATH="/var/www/petsbook-flight"
LOCAL_PATH="."

echo "🚀 Начинаем деплой на $SERVER_IP..."

# 1. Создаём .env.production из примера если его нет
if [ ! -f ".env.production" ]; then
    echo "📝 Создаём .env.production из примера..."
    cp env.production.example .env.production
    echo "⚠️  ВАЖНО: Заполни .env.production реальными значениями перед деплоем!"
    echo "   - DB_HOST, DB_NAME, DB_USER, DB_PASSWORD"
    echo "   - CORS_ALLOWED_ORIGINS"
    echo "   - Другие секреты"
    exit 1
fi

# 2. Проверяем, что .env.production заполнен
if grep -q "YOUR_" .env.production || grep -q "example" .env.production; then
    echo "❌ .env.production содержит примеры! Заполни реальными значениями."
    exit 1
fi

# 3. Копируем файлы на сервер
echo "📤 Копируем файлы на сервер..."
rsync -avz --exclude='.git' --exclude='node_modules' --exclude='vendor' \
    --exclude='logs/*' --exclude='.env' \
    "$LOCAL_PATH/" "$SERVER_USER@$SERVER_IP:$PROJECT_PATH/"

# 4. Копируем .env.production отдельно
echo "🔐 Копируем .env.production..."
scp .env.production "$SERVER_USER@$SERVER_IP:$PROJECT_PATH/"

# 5. Устанавливаем зависимости на сервере
echo "📦 Устанавливаем зависимости..."
ssh "$SERVER_USER@$SERVER_IP" "cd $PROJECT_PATH && composer install --no-dev --optimize-autoloader"

# 6. Останавливаем старые контейнеры
echo "🛑 Останавливаем старые контейнеры..."
ssh "$SERVER_USER@$SERVER_IP" "cd $PROJECT_PATH && docker compose -f docker-compose.prod.yml down"

# 7. Запускаем новые контейнеры
echo "🚀 Запускаем новые контейнеры..."
ssh "$SERVER_USER@$SERVER_IP" "cd $PROJECT_PATH && docker compose -f docker-compose.prod.yml up -d --build"

# 8. Проверяем статус
echo "✅ Проверяем статус..."
ssh "$SERVER_USER@$SERVER_IP" "cd $PROJECT_PATH && docker compose -f docker-compose.prod.yml ps"

# 9. Тестируем API
echo "🧪 Тестируем API..."
sleep 5
if curl -f -s "https://$SERVER_IP/api/test" > /dev/null; then
    echo "🎉 Деплой успешен! API работает."
else
    echo "⚠️  API не отвечает. Проверь логи:"
    ssh "$SERVER_USER@$SERVER_IP" "docker exec petsbook-php-prod tail -10 /var/www/html/logs/php_errors.log"
fi

echo "🏁 Деплой завершён!" 