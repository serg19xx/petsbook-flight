#!/bin/bash

# Генерация секретов для GitHub Actions
echo "🔧 Генерация секретов для GitHub Actions..."

# 1. Генерируем JWT секрет
echo "🔑 Генерируем JWT_SECRET..."
JWT_SECRET=$(openssl rand -base64 32)
echo "JWT_SECRET=$JWT_SECRET"
echo ""

# 2. Генерируем SSH ключ (если нет)
if [ ! -f ~/.ssh/id_rsa ]; then
    echo "🔐 Генерируем SSH ключ..."
    ssh-keygen -t rsa -b 4096 -C "github-actions@petsbook.ca" -f ~/.ssh/id_rsa -N ""
    echo "✅ SSH ключ создан"
else
    echo "✅ SSH ключ уже существует"
fi

# 3. Показываем публичный ключ
echo ""
echo "📋 SSH_PUBLIC_KEY (добавь на сервер):"
cat ~/.ssh/id_rsa.pub
echo ""

# 4. Показываем приватный ключ
echo "🔐 SSH_PRIVATE_KEY (добавь в GitHub Secrets):"
cat ~/.ssh/id_rsa
echo ""

# 5. Создаём файл с секретами для копирования
cat > github-secrets.txt << EOF
# GitHub Secrets для автоматического деплоя
# Добавь эти секреты в GitHub → Settings → Secrets and variables → Actions

SSH_PRIVATE_KEY:
$(cat ~/.ssh/id_rsa)

SERVER_IP:
64.188.10.53

SERVER_USER:
root

PROJECT_PATH:
/var/www/petsbook-flight

DB_DATABASE:
petsbook_prod

DB_USERNAME:
petsbook_user

DB_PASSWORD:
ЗАМЕНИ_НА_РЕАЛЬНЫЙ_ПАРОЛЬ

DB_ROOT_PASSWORD:
ЗАМЕНИ_НА_РЕАЛЬНЫЙ_ROOT_ПАРОЛЬ

JWT_SECRET:
$JWT_SECRET

MAIL_SENDER_EMAIL:
noreply@petsbook.ca

MAIL_SENDER_PHONE:
+1-555-123-4567

SENDGRID_API_KEY:
ЗАМЕНИ_НА_РЕАЛЬНЫЙ_SENDGRID_КЛЮЧ

CORS_ALLOWED_ORIGINS:
https://site.petsbook.ca,https://64.188.10.53,http://localhost:5173

GOOGLE_TRANSLATE_API_KEY:
ЗАМЕНИ_НА_РЕАЛЬНЫЙ_GOOGLE_TRANSLATE_КЛЮЧ
EOF

echo "📄 Секреты сохранены в github-secrets.txt"
echo ""
echo "🎯 Следующие шаги:"
echo "1. Добавь SSH публичный ключ на сервер:"
echo "   ssh root@64.188.10.53 \"echo '$(cat ~/.ssh/id_rsa.pub)' >> ~/.ssh/authorized_keys\""
echo ""
echo "2. Открой github-secrets.txt и добавь секреты в GitHub"
echo "3. Заполни реальные значения (пароли, ключи API)"
echo "4. Сделай push в main - деплой запустится автоматически!" 