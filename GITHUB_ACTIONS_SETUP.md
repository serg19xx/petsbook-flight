# 🔧 Настройка GitHub Actions для автоматического деплоя

## 🎯 Что это даёт

- **Автоматический деплой** при каждом push в main/master
- **Безопасность** — все секреты в GitHub Secrets
- **Надёжность** — тестирование API после деплоя
- **Простота** — один push = деплой на сервер

## 📋 Настройка GitHub Secrets

### 1. Перейди в настройки репозитория
```
GitHub → Your Repository → Settings → Secrets and variables → Actions
```

### 2. Добавь следующие секреты

#### 🔐 SSH ключи
```
SSH_PRIVATE_KEY = -----BEGIN OPENSSH PRIVATE KEY-----
                  (содержимое твоего приватного SSH ключа)
                  -----END OPENSSH PRIVATE KEY-----
```





#### 🔑 JWT
```
JWT_SECRET = случайная_строка_минимум_32_символа
```

#### 📧 Email (SendGrid)
```
SENDGRID_API_KEY = твой_sendgrid_api_ключ
```



#### 🔤 Google Translate (опционально)
```
GOOGLE_TRANSLATE_API_KEY = твой_google_translate_api_ключ
```

## 🔑 Генерация SSH ключа

### 1. Создай SSH ключ (если нет)
```bash
ssh-keygen -t rsa -b 4096 -C "github-actions@petsbook.ca"
```

### 2. Добавь публичный ключ на сервер
```bash
# Скопируй содержимое ~/.ssh/id_rsa.pub
cat ~/.ssh/id_rsa.pub

# Добавь на сервер
ssh root@64.188.10.53 "echo 'твой_публичный_ключ' >> ~/.ssh/authorized_keys"
```

### 3. Добавь приватный ключ в GitHub Secrets
```bash
# Скопируй содержимое ~/.ssh/id_rsa
cat ~/.ssh/id_rsa
```

## 🚀 Как работает автоматический деплой

### Триггеры
- **Push в main/master** — автоматический деплой
- **Manual trigger** — можно запустить вручную в GitHub

### Процесс
1. **Checkout** кода
2. **Setup PHP** и Composer
3. **Install** зависимости
4. **Setup SSH** соединение
5. **Create** .env.production из секретов
6. **Deploy** файлы на сервер
7. **Install** зависимости на сервере
8. **Restart** Docker контейнеры
9. **Test** API
10. **Report** статус

## 📊 Мониторинг

### В GitHub
- **Actions** → **Deploy to Production** → **View logs**
- Зелёная галочка = успех
- Красный крест = ошибка

### На сервере
```bash
# Статус контейнеров
ssh root@64.188.10.53 "cd /var/www/petsbook-flight && docker compose -f docker-compose.prod.yml ps"

# Логи PHP
ssh root@64.188.10.53 "docker exec petsbook-php-prod tail -f /var/www/html/logs/php_errors.log"
```

## 🐛 Отладка

### Если деплой падает
1. **Проверь логи** в GitHub Actions
2. **Убедись**, что все секреты добавлены
3. **Проверь SSH** соединение
4. **Проверь права** на сервере

### Полезные команды
```bash
# Тест SSH соединения
ssh root@64.188.10.53 "echo 'SSH работает'"

# Проверка места на сервере
ssh root@64.188.10.53 "df -h"

# Проверка Docker
ssh root@64.188.10.53 "docker --version && docker compose --version"
```

## 🎉 Результат

После настройки:
- ✅ **Push в main** = автоматический деплой
- ✅ **Все секреты** в безопасности
- ✅ **API тестируется** после деплоя
- ✅ **Логи** доступны в GitHub
- ✅ **Никаких ручных** действий

## 📝 Примечания

- **Первый деплой** может занять 5-10 минут
- **Последующие деплои** быстрее (2-3 минуты)
- **При ошибках** проверяй логи в GitHub Actions
- **Секреты** можно обновлять без пересборки 