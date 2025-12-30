#!/bin/bash
set -e

# Set APP_URL from Render's environment variable if available
if [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

# Caching configuration
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Start Apache
echo "Starting Apache..."
exec apache2-foreground
