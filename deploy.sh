#!/bin/bash

# Deploy script for PetsBook production
set -e

echo "🚀 Deploying to production..."

# Check if we should skip git operations
if [ "$1" = "--no-git" ]; then
    echo "⏭️  Skipping git operations..."
else
    # Check SSH connection to GitHub
    echo "🔑 Checking SSH connection to GitHub..."
    if ssh -T git@github.com 2>&1 | grep -q "successfully authenticated"; then
        echo "✅ SSH connection to GitHub successful"
        
        # Git operations
        echo "📝 Committing changes..."
        git add .
        git commit -m "Deploy to production - $(date '+%Y-%m-%d %H:%M:%S')"

        echo "📤 Pushing to GitHub..."
        git push origin main
    else
        echo "⚠️  SSH connection to GitHub failed"
        echo "💡 You can:"
        echo "   1. Set up SSH keys: https://docs.github.com/en/authentication/connecting-to-github-with-ssh"
        echo "   2. Run with --no-git flag: ./deploy.sh --no-git"
        echo "   3. Continue without git operations (press Enter)"
        read -p "Continue without git? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "❌ Deployment cancelled"
            exit 1
        fi
    fi
fi

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

# Test API endpoint
print_status "Testing API endpoint..."
sleep 5
if curl -f -s http://64.188.10.53/api/i18n/locales > /dev/null; then
    print_status "✅ API is working correctly!"
else
    print_warning "⚠️  API test failed. Check logs with: docker-compose logs"
fi

# Show logs
print_status "Recent logs:"
docker-compose logs --tail=20

print_status "🎉 Deployment completed!"
print_status "🌐 Your app should be available at: http://64.188.10.53"
print_status "To view logs: docker-compose logs -f"
print_status "To stop: docker-compose down" 