#!/bin/sh
# Arranque del contenedor. El servicio "app" (CONTAINER_ROLE=app) prepara la clave y la base de datos;
# los servicios "queue" y "scheduler" solo leen la clave compartida y ejecutan su comando.
set -e

cd /var/www/html

# .env de la raíz es un enlace a este archivo del volumen: así todos los contenedores, y también
# `docker compose exec`, leen la misma APP_KEY.
ENV_FILE=storage/app/docker/.env
as_www() { runuser -u www-data -- "$@"; }

# El volumen de storage puede llegar vacío: recrear la estructura que Laravel espera.
mkdir -p storage/app/public storage/app/private storage/app/docker storage/logs \
         storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R www-data:www-data storage bootstrap/cache

# APP_KEY: se genera una sola vez (la primera vez que arranca "app") y se conserva en el volumen.
if [ "$CONTAINER_ROLE" = "app" ] && ! grep -qs '^APP_KEY=.\+' "$ENV_FILE"; then
    echo "APP_KEY=$(php artisan key:generate --show)" > "$ENV_FILE"
    chown www-data:www-data "$ENV_FILE" && chmod 600 "$ENV_FILE"
    echo "APP_KEY generada y guardada en $ENV_FILE"
fi

if [ "$CONTAINER_ROLE" = "app" ]; then
    echo "Esperando la base de datos y ejecutando migraciones..."
    tries=0
    until as_www php artisan migrate --force; do
        tries=$((tries + 1))
        [ "$tries" -ge 30 ] && echo "La base de datos no respondió a tiempo." && exit 1
        sleep 2
    done

    # Siembra solo la primera vez (base sin usuarios). Los datos demo solo se crean con APP_ENV=local.
    if [ "${DB_SEED:-true}" = "true" ]; then
        users="$(XDG_CONFIG_HOME=/tmp as_www php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n 1)"
        if [ "$users" = "0" ]; then
            as_www php artisan db:seed --force
        fi
    fi
fi

# Apache arranca como root y baja a www-data por sí mismo; el resto de comandos corre como www-data.
if [ "$1" = "apache2-foreground" ]; then
    exec "$@"
fi
exec runuser -u www-data -- "$@"
