# 🔧 Захардкоженные значения в deploy.yml

## 📧 Email контакты
```bash
MAIL_SENDER_EMAIL=email@mail.com
MAIL_SENDER_PHONE=3123423412
```

## 🌍 CORS домены
```bash
CORS_ALLOWED_ORIGINS=https://site.petsbook.ca,https://64.188.10.53,http://localhost:5173
```

## 🌐 Сервер
```bash
APP_URL=https://64.188.10.53
SERVER_IP=64.188.10.53
SERVER_USER=root
PROJECT_PATH=/var/www/petsbook-flight
```

## 🗄️ База данных
```bash
DB_NAME=petsbook_new
DB_USER=petsbook_serg
DB_PASSWORD=your_secure_password_here
DB_ROOT_PASSWORD=your_secure_root_password_here
```

## 🎯 Преимущества

### ✅ Быстрое изменение
- Не нужно менять GitHub Secrets
- Не нужно перезапускать деплой
- Изменения применяются сразу при следующем push

### ✅ Простота
- Меньше секретов для управления
- Меньше ошибок конфигурации
- Легче отлаживать

### ✅ Безопасность
- Конфиденциальные данные (пароли, ключи) остаются в секретах
- Публичные данные (домены, контакты) в коде

## 🔄 Как изменить

### Email контакты
1. Открой `.github/workflows/deploy.yml`
2. Найди строки:
   ```yaml
   MAIL_SENDER_EMAIL=email@mail.com
   MAIL_SENDER_PHONE=3123423412
   ```
3. Измени значения
4. Сделай push в main/master

### CORS домены
1. Открой `.github/workflows/deploy.yml`
2. Найди строку:
   ```yaml
   CORS_ALLOWED_ORIGINS=https://site.petsbook.ca,https://64.188.10.53,http://localhost:5173
   ```
3. Добавь/удали домены
4. Сделай push в main/master

## 📝 Примечания

- **Автоматический деплой** произойдёт при push в main/master
- **Изменения** применятся через 2-3 минуты
- **Логи** можно посмотреть в GitHub Actions
- **Откат** - просто измени обратно и сделай push

## 🚀 Результат

Теперь для изменения email контактов или CORS доменов достаточно:
1. Изменить значения в `deploy.yml`
2. Сделать push
3. Ждать автоматического деплоя

Никаких ручных действий на сервере не требуется! 🎉 