# 🚀 Автоматический Деплой PetsBook

## Быстрый старт

### 1. Первоначальная настройка
```bash
# Создаёт .env.production из примера
./setup-production.sh
```

### 2. Заполни переменные окружения
Открой `.env.production` и заполни реальными значениями:
- `DB_NAME` - имя базы данных
- `DB_USER` - пользователь MySQL  
- `DB_PASSWORD` - пароль MySQL
- `JWT_SECRET` - случайная строка (минимум 32 символа)
- `SENDGRID_API_KEY` - ключ SendGrid
- `GOOGLE_TRANSLATE_API_KEY` - ключ Google Translate

### 3. Деплой
```bash
./deploy.sh
```

## 🔧 Что делает автоматический деплой

1. **Проверяет** `.env.production` на корректность
2. **Копирует** все файлы на сервер (исключая .git, vendor, logs)
3. **Устанавливает** зависимости через composer
4. **Останавливает** старые контейнеры
5. **Запускает** новые контейнеры с пересборкой
6. **Тестирует** API
7. **Показывает** статус

## 📁 Структура файлов

```
petsbook-flight/
├── deploy.sh              # Автоматический деплой
├── setup-production.sh    # Настройка окружения
├── .env.production        # Продакшн переменные (не в git)
├── env.production.example # Пример переменных
├── docker-compose.prod.yml # Продакшн контейнеры
└── docker/nginx/nginx.prod.conf # Продакшн nginx
```

## 🔒 Безопасность

- `.env.production` не попадает в git
- Все секреты хранятся локально
- SSL сертификаты на сервере
- CORS настроен для продакшн доменов

## 🐛 Отладка

### Проверить логи PHP
```bash
ssh root@64.188.10.53 "docker exec petsbook-php-prod tail -f /var/www/html/logs/php_errors.log"
```

### Проверить статус контейнеров
```bash
ssh root@64.188.10.53 "cd /var/www/petsbook-flight && docker compose -f docker-compose.prod.yml ps"
```

### Перезапустить контейнеры
```bash
ssh root@64.188.10.53 "cd /var/www/petsbook-flight && docker compose -f docker-compose.prod.yml restart"
```

## 🎯 Результат

После успешного деплоя:
- ✅ API работает на https://64.188.10.53
- ✅ CORS настроен для https://site.petsbook.ca
- ✅ SSL сертификаты работают
- ✅ Все переменные окружения загружены
- ✅ База данных подключена

## 📝 Примечания

- Деплой занимает 2-3 минуты
- При ошибках проверяй логи PHP
- Все файлы автоматически синхронизируются
- Никаких ручных настроек на сервере не требуется 