#!/bin/bash

echo "🔐 Generating secure secrets for production..."

# Generate JWT secret
JWT_SECRET=$(openssl rand -base64 64)
echo "JWT_SECRET=$JWT_SECRET"

# Generate database passwords
DB_PASSWORD=$(openssl rand -base64 32)
DB_ROOT_PASSWORD=$(openssl rand -base64 32)

echo "DB_PASSWORD=$DB_PASSWORD"
echo "DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD"

echo ""
echo "📝 Copy these values to your .env file:"
echo "========================================"
echo "JWT_SECRET=$JWT_SECRET"
echo "DB_PASSWORD=$DB_PASSWORD"
echo "DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD"
echo "========================================"
echo ""
echo "⚠️  Keep these secrets secure and never commit them to git!" 