#!/bin/bash

echo "🚀 Deploying to production..."

# Stop containers
echo "📦 Stopping containers..."
docker-compose down

# Copy production env
echo "⚙️  Setting up production environment..."
cp .env.production .env

# Start containers
echo "🚀 Starting containers..."
docker-compose up -d

# Check status
echo "✅ Checking container status..."
docker-compose ps

echo "🎉 Deployment completed!"
echo "🌐 Your app should be available at: http://64.188.10.53" 