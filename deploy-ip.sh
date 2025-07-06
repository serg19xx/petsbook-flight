#!/bin/bash

echo "🚀 Deploying PetsBook to IP server 64.188.10.53..."

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if .env exists
if [ ! -f .env ]; then
    print_error ".env file not found!"
    print_status "Creating .env from template..."
    cp env.production.example .env
    print_warning "Please edit .env file with your production values"
    print_warning "Run: nano .env"
    exit 1
fi

# Stop existing containers
print_status "Stopping existing containers..."
docker-compose -f docker-compose.prod.yml down --remove-orphans

# Build and start
print_status "Building and starting containers..."
docker-compose -f docker-compose.prod.yml up --build -d

# Wait for startup
print_status "Waiting for services to start..."
sleep 20

# Check status
print_status "Container status:"
docker-compose -f docker-compose.prod.yml ps

# Test API
print_status "Testing API..."
sleep 10
if curl -f -s http://64.188.10.53/api/i18n/locales > /dev/null; then
    print_status "✅ API is working!"
else
    print_warning "⚠️  API test failed. Checking logs..."
    docker-compose -f docker-compose.prod.yml logs --tail=10
fi

print_status "🎉 Deployment completed!"
print_status "Your app is running at: http://64.188.10.53"
print_status "API endpoint: http://64.188.10.53/api/i18n/locales"
print_status "View logs: docker-compose -f docker-compose.prod.yml logs -f" 