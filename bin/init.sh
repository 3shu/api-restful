#!/usr/bin/env bash

# Initialization script for the project
# This script helps set up the project for first-time use

set -e

echo "╔════════════════════════════════════════════════════════════╗"
echo "║        Symfony Multi-Database API - Initialization         ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Error: Docker is not running. Please start Docker Desktop."
    exit 1
fi

echo "✅ Docker is running"
echo ""

# Check if .env.local exists
if [ ! -f .env.local ]; then
    echo "📝 Creating .env.local file..."
    cat > .env.local << EOF
# Local Environment Configuration
APP_ENV=dev
USE_AWS_SECRETS=false

# AWS Credentials (uncomment and fill when ready to test AWS)
# AWS_ACCESS_KEY_ID=your_key_here
# AWS_SECRET_ACCESS_KEY=your_secret_here
# AWS_REGION=us-east-1
EOF
    echo "✅ .env.local created"
else
    echo "✅ .env.local already exists"
fi

echo ""
echo "🐳 Building and starting Docker containers..."
docker-compose up -d --build

echo ""
echo "⏳ Waiting for services to be healthy..."
sleep 10

echo ""
echo "📦 Installing PHP dependencies..."
docker exec api-restful-php composer install --no-interaction

echo ""
echo "🧪 Running health checks..."
docker exec api-restful-php php bin/health-check || true

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║                    SETUP COMPLETE!                         ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "🌐 Application URL: http://localhost:8080"
echo "📊 MySQL: localhost:3306 (root/mysql_secret)"
echo "📊 SQL Server: localhost:1433 (sa/SqlServer2024!)"
echo "📊 Redis: localhost:6379"
echo ""
echo "Next steps:"
echo "  1. Configure AWS credentials in .env.local (if using AWS Secrets)"
echo "  2. Run: docker exec -it api-restful-php bash"
echo "  3. Proceed to Phase 3: User API implementation"
echo ""
