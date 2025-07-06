#!/bin/bash

# Deploy script for PetsBook production
set -e

echo "🚀 Starting PetsBook production deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if .env file exists
if [ ! -f .env ]; then
    print_error ".env file not found! Please create it from env.production.example"
    exit 1
fi

# Check if .env file exists
if [ ! -f .env ]; then
    print_error ".env file not found! Please create it from env.production.example"
    exit 1
fi

# Stop existing containers
print_status "Stopping existing containers..."
docker-compose -f docker-compose.prod.yml down --remove-orphans

# Build and start production containers
print_status "Building and starting production containers..."
docker-compose -f docker-compose.prod.yml up --build -d

# Wait for MySQL to be ready
print_status "Waiting for MySQL to be ready..."
sleep 30

# Check if containers are running
print_status "Checking container status..."
docker-compose -f docker-compose.prod.yml ps

# Test API endpoint
print_status "Testing API endpoint..."
sleep 10
if curl -f -s http://64.188.10.53/api/i18n/locales > /dev/null; then
    print_status "✅ API is working correctly!"
else
    print_warning "⚠️  API test failed. Check logs with: docker-compose -f docker-compose.prod.yml logs"
fi

# Show logs
print_status "Recent logs:"
docker-compose -f docker-compose.prod.yml logs --tail=20

print_status "🎉 Deployment completed!"
print_status "Your application is now running at: http://64.188.10.53"
print_status "To view logs: docker-compose -f docker-compose.prod.yml logs -f"
print_status "To stop: docker-compose -f docker-compose.prod.yml down" 