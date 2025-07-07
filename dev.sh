#!/bin/bash

echo "🛠️  Starting local development..."

# Stop containers
echo "📦 Stopping containers..."
docker-compose down

# Copy development env
echo "⚙️  Setting up development environment..."
cp .env.development .env

# Start containers
echo "🚀 Starting containers..."
docker-compose up -d

# Check status
echo "✅ Checking container status..."
docker-compose ps

echo "🎉 Local development started!"
echo "🌐 Your app should be available at: http://localhost:8080"
echo "📝 To view logs: docker-compose logs -f"
echo "🛑 To stop: docker-compose down" 