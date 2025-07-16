# 🐾 PetsBook API

Backend API для социальной сети PetsBook с поддержкой многоязычности и автоматическим деплоем.

## 🚀 Быстрый старт

### Локальная разработка
```bash
# Клонируй репозиторий
git clone <repository-url>
cd petsbook-flight

# Запусти локально
docker compose up -d
```

### Продакшн деплой

#### Вариант 1: GitHub Actions (Рекомендуется)
```bash
# 1. Сгенерируй секреты
./generate-secrets.sh

# 2. Добавь SSH ключ на сервер
ssh root@64.188.10.53 "echo 'твой_публичный_ключ' >> ~/.ssh/authorized_keys"

# 3. Добавь секреты в GitHub
# GitHub → Settings → Secrets and variables → Actions

# 4. Push в main = автоматический деплой!
git push origin main
```

#### Вариант 2: Ручной деплой
```bash
# 1. Настрой окружение
./setup-production.sh

# 2. Заполни .env.production

# 3. Деплой
./deploy.sh
```

## 📁 Структура проекта

```
petsbook-flight/
├── .github/workflows/     # GitHub Actions
├── src/                   # PHP код
├── public/               # Публичные файлы
├── docker/               # Docker конфигурация
├── database/             # Миграции и процедуры
├── deploy.sh             # Скрипт ручного деплоя
├── setup-production.sh   # Настройка окружения
└── generate-secrets.sh   # Генерация секретов
```

## 🔧 Технологии

- **PHP 8.2** с FlightPHP
- **MySQL** база данных
- **Docker** контейнеризация
- **Nginx** веб-сервер
- **GitHub Actions** автоматический деплой
- **SendGrid** отправка email
- **Gmail SMTP** отправка email
- **Google Translate** API

## 🌍 API Endpoints

### Аутентификация
- `POST /api/auth/register` - Регистрация
- `POST /api/auth/login` - Вход
- `POST /api/auth/logout` - Выход

### Пользователи
- `GET /api/users/profile` - Профиль
- `PUT /api/users/profile` - Обновление профиля
- `POST /api/users/avatar` - Загрузка аватара

### Интернационализация
- `GET /api/i18n/locales` - Доступные языки
- `GET /api/i18n/translated-languages` - Переведённые языки
- `POST /api/i18n/translate` - Перевод текста

## 🔒 Безопасность

- JWT токены для аутентификации
- CORS настроен для продакшн доменов
- SSL сертификаты на сервере
- Все секреты в GitHub Secrets
- Валидация входных данных

## 📧 Email провайдеры

### SendGrid API
```env
MAIL_DRIVER=sendgrid_api
SENDGRID_API_KEY=your_api_key
SENDGRID_FROM_ADDRESS=noreply@petsbook.ca
SENDGRID_FROM_NAME=PetsBook
```

### SendGrid SMTP
```env
MAIL_DRIVER=sendgrid_smtp
SENDGRID_SMTP_PASSWORD=your_smtp_password
SENDGRID_FROM_ADDRESS=noreply@petsbook.ca
SENDGRID_FROM_NAME=PetsBook
```

### Gmail SMTP
```env
MAIL_DRIVER=gmail_smtp
GMAIL_USERNAME=your_email@gmail.com
GMAIL_APP_PASSWORD=your_app_password
GMAIL_FROM_ADDRESS=your_email@gmail.com
GMAIL_FROM_NAME=PetsBook
```

**Важно для Gmail:** Используйте App Password вместо обычного пароля. Для получения App Password:
1. Включите 2FA в Google аккаунте
2. Перейдите в [Google Account Settings](https://myaccount.google.com/apppasswords)
3. Сгенерируйте App Password для "Mail"

## 📊 Мониторинг

### GitHub Actions
- Автоматические деплои при push в main
- Тестирование API после деплоя
- Логи доступны в GitHub

### Сервер
```bash
# Статус контейнеров
ssh root@64.188.10.53 "cd /var/www/petsbook-flight && docker compose -f docker-compose.prod.yml ps"

# Логи PHP
ssh root@64.188.10.53 "docker exec petsbook-php-prod tail -f /var/www/html/logs/php_errors.log"
```

## 🐛 Отладка

### Локально
```bash
# Логи контейнеров
docker compose logs -f

# Вход в PHP контейнер
docker compose exec php bash
```

### Продакшн
```bash
# Логи PHP
ssh root@64.188.10.53 "docker exec petsbook-php-prod tail -f /var/www/html/logs/php_errors.log"

# Перезапуск контейнеров
ssh root@64.188.10.53 "cd /var/www/petsbook-flight && docker compose -f docker-compose.prod.yml restart"
```

## 📝 Документация

- [Деплой](DEPLOYMENT.md) - Подробная инструкция по деплою
- [GitHub Actions](GITHUB_ACTIONS_SETUP.md) - Настройка автоматического деплоя
- [API документация](API.md) - Описание всех endpoints

## 🎯 Результат

После настройки:
- ✅ **Push в main** = автоматический деплой
- ✅ **API работает** на https://64.188.10.53
- ✅ **CORS настроен** для https://site.petsbook.ca
- ✅ **SSL сертификаты** работают
- ✅ **Все секреты** в безопасности
- ✅ **Никаких ручных** настроек

## 🤝 Вклад в проект

1. Fork репозитория
2. Создай feature branch
3. Сделай изменения
4. Создай Pull Request

## 📄 Лицензия

MIT License 