#!/bin/sh

# Check if the artisan file exists
if [ ! -f "$APP_BASE_DIR/artisan" ]; then
    echo "❌ Artisan file not found in $APP_BASE_DIR"
    exit 1
fi

echo "⚡ Running Filament optimize..."
php "$APP_BASE_DIR/artisan" filament:optimize
exit 0