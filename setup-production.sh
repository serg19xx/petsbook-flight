#!/bin/bash

# Скрипт настройки продакшн окружения
echo "🔧 Настройка продакшн окружения..."

# 1. Создаём .env.production если его нет
if [ ! -f ".env.production" ]; then
    echo "📝 Создаём .env.production..."
    cp env.production.example .env.production
    echo "✅ Файл создан! Теперь заполни его реальными значениями."
    echo ""
    echo "🔑 Что нужно заполнить:"
    echo "   - DB_DATABASE: имя базы данных"
    echo "   - DB_USERNAME: пользователь MySQL"
    echo "   - DB_PASSWORD: пароль MySQL"
    echo "   - JWT_SECRET: случайная строка минимум 32 символа"
    echo "   - SENDGRID_API_KEY: ключ SendGrid"
    echo "   - GOOGLE_TRANSLATE_API_KEY: ключ Google Translate"
    echo ""
    echo "💡 Пример генерации JWT_SECRET:"
    echo "   openssl rand -base64 32"
    echo ""
    echo "📝 После заполнения запусти: ./deploy.sh"
else
    echo "✅ .env.production уже существует"
fi

# 2. Делаем скрипты исполняемыми
chmod +x deploy.sh
chmod +x setup-production.sh

echo "🎯 Готово! Теперь:"
echo "   1. Заполни .env.production"
echo "   2. Запусти ./deploy.sh" 