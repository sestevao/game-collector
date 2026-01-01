#!/bin/bash
set -e

# Set APP_URL from Render's environment variable if available
if [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

echo "Environment check:"
php -v
node -v
npm -v

# Caching configuration
echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Run seeders (only if you want to seed on every deploy)
echo "Running seeders..."
php artisan db:seed --force


# Start Apache
echo "Starting Apache..."
exec apache2-foreground
