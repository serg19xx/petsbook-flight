# 🚀 PetsBook Production Deployment Guide

## Prerequisites

- Docker and Docker Compose installed on server
- Server IP address: 64.188.10.53
- SendGrid API key for email sending
- Google Translate API key (optional)

## 📋 Pre-deployment Checklist

### 1. Server Configuration
Ensure your server is accessible at `http://64.188.10.53`

### 2. Environment Configuration
Copy the example environment file and configure it:
```bash
cp env.production.example .env
```

Edit `.env` with your production values:
- Database passwords
- JWT secret (generate a strong random string)
- SendGrid API key
- Google Translate API key
- Domain settings

### 3. Database Setup
The database will be automatically created and migrated when containers start.

## 🚀 Deployment Steps

### Option 1: Automated Deployment
```bash
# Make deploy script executable
chmod +x deploy.sh

# Run deployment
./deploy.sh
```

### Option 2: Manual Deployment
```bash
# Stop any existing containers
docker-compose -f docker-compose.prod.yml down --remove-orphans

# Build and start production containers
docker-compose -f docker-compose.prod.yml up --build -d

# Check status
docker-compose -f docker-compose.prod.yml ps

# View logs
docker-compose -f docker-compose.prod.yml logs -f
```

## 🔧 Configuration Files

### Production Docker Compose
- `docker-compose.prod.yml` - Production container configuration
- Uses persistent volumes for database
- Includes SSL support
- Optimized for production

### Production Nginx
- `docker/nginx/nginx.prod.conf` - Production nginx configuration
- SSL/TLS configuration
- Security headers
- Gzip compression
- API routing

## 📊 Monitoring

### View Logs
```bash
# All services
docker-compose -f docker-compose.prod.yml logs -f

# Specific service
docker-compose -f docker-compose.prod.yml logs -f nginx
docker-compose -f docker-compose.prod.yml logs -f php
docker-compose -f docker-compose.prod.yml logs -f mysql
```

### Check Status
```bash
docker-compose -f docker-compose.prod.yml ps
```

### Test API
```bash
curl http://64.188.10.53/api/i18n/locales
```

## 🔄 Updates

### Update Application
```bash
# Pull latest code
git pull origin main

# Rebuild and restart
docker-compose -f docker-compose.prod.yml up --build -d
```

### Update Dependencies
```bash
# Rebuild PHP container with new dependencies
docker-compose -f docker-compose.prod.yml build php
docker-compose -f docker-compose.prod.yml up -d
```

## 🛠️ Troubleshooting

### Common Issues

1. **Server Connection Errors**
   - Verify server is accessible at `http://64.188.10.53`
   - Check firewall settings

2. **Database Connection Issues**
   - Check `.env` database configuration
   - Verify MySQL container is running

3. **API Not Responding**
   - Check nginx logs: `docker-compose -f docker-compose.prod.yml logs nginx`
   - Check PHP logs: `docker-compose -f docker-compose.prod.yml logs php`

4. **Email Not Sending**
   - Verify SendGrid API key in `.env`
   - Check mail service logs

### Useful Commands

```bash
# Restart specific service
docker-compose -f docker-compose.prod.yml restart nginx

# Access container shell
docker-compose -f docker-compose.prod.yml exec php bash

# View real-time logs
docker-compose -f docker-compose.prod.yml logs -f --tail=100

# Check disk usage
docker system df

# Clean up unused resources
docker system prune -a
```

## 🔒 Security Considerations

- All sensitive data is in `.env` file (not committed to git)
- SSL certificates are mounted as volumes
- Security headers are configured in nginx
- Database passwords should be strong and unique
- JWT secret should be long and random

## 📈 Performance Optimization

- Gzip compression enabled
- Static file caching configured
- Database connection pooling
- PHP-FPM optimized settings
- Nginx worker processes optimized

## 🆘 Support

If you encounter issues:
1. Check the logs first
2. Verify all prerequisites are met
3. Ensure all configuration files are correct
4. Test individual components

For additional help, check the application logs and Docker container status. 