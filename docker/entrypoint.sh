#!/bin/sh
set -e
cd /var/www/html

# Render da APP_KEY como texto aleatorio: Laravel necesita "base64:" + 32 bytes
if [ -n "$APP_KEY" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
  APP_KEY="base64:$(printf '%s' "$APP_KEY" | openssl dgst -sha256 -binary | base64)"
  export APP_KEY
fi
export PORT="${PORT:-10000}"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/uploads/productos

php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true
chown -R www-data:www-data storage bootstrap/cache public/uploads

# Espera a la base de datos y migra
for i in 1 2 3 4 5 6 7 8 9 10; do
  php artisan migrate --force && break
  echo "Base de datos no disponible todavía, reintento $i..."; sleep 5
done
php artisan db:seed --class=DemoDataSeeder --force || true

# Programador de tareas (genera los listados quincenales a las 06:00)
su -s /bin/sh www-data -c "php artisan schedule:work" > /dev/null 2>&1 &

exec apache2-foreground
