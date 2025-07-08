#!/bin/bash

# 🔍 Проверка и обновление GitHub Secrets для PetsBook

set -e

echo "🔍 Проверка GitHub Secrets для автоматического деплоя..."
echo ""

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Функция для проверки секрета
check_secret() {
    local secret_name=$1
    local description=$2
    
    echo -n "🔑 $secret_name: "
    
    # Проверяем через GitHub CLI (если установлен)
    if command -v gh &> /dev/null; then
        if gh secret list 2>/dev/null | grep -q "$secret_name"; then
            echo -e "${GREEN}✅ Найден${NC}"
        else
            echo -e "${RED}❌ Отсутствует${NC}"
            echo "   📝 $description"
        fi
    else
        echo -e "${YELLOW}⚠️  GitHub CLI не установлен${NC}"
        echo "   📝 Проверь вручную: GitHub → Settings → Secrets → Actions"
    fi
}

echo "📋 Необходимые секреты:"
echo ""

# SSH
check_secret "SSH_PRIVATE_KEY" "Приватный SSH ключ для подключения к серверу"





# JWT
check_secret "JWT_SECRET" "Секретный ключ JWT (минимум 32 символа)"

# Email
check_secret "SENDGRID_API_KEY" "API ключ SendGrid"



# Google Translate (опционально)
check_secret "GOOGLE_TRANSLATE_API_KEY" "API ключ Google Translate (опционально)"

echo ""
echo "📝 Инструкции по добавлению секретов:"
echo "1. Перейди в GitHub → Settings → Secrets and variables → Actions"
echo "2. Нажми 'New repository secret'"
echo "3. Добавь каждый секрет с правильным именем"
echo ""
echo "⚠️  ВАЖНО: Используй именно эти имена секретов!"
echo "   - DB_NAME (НЕ DB_DATABASE)"
echo "   - DB_USER (НЕ DB_USERNAME)"
echo "   - Все остальные имена точно как указано выше"
echo ""
echo "🔗 Подробная инструкция: GITHUB_ACTIONS_SETUP.md" 