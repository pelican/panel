#!/bin/ash -e
# shellcheck shell=dash

# check for .env file or symlink and generate app keys if missing
if [ -f /pelican-data/.env ]; then
  echo ".env vars exist."
  # load specific env vars from .env used in the entrypoint if they are not already set
  for VAR in APP_KEY APP_INSTALLED DB_CONNECTION DB_HOST DB_PORT TRUSTED_PROXIES; do
    echo "checking for ${VAR}"

    # the container environment takes precedence, matching Laravel's own behavior
    eval "CURRENT=\${${VAR}:-}"
    if [ -n "${CURRENT}" ]; then
      echo "${VAR} already set in environment, skipping"
      continue
    fi

    # match only a real assignment at the start of a line, never comments or
    # other variables that merely contain the name
    if ! LINE=$(grep -m1 "^${VAR}=" .env); then
      echo "didn't find variable to set"
      continue
    fi

    ## skip if it looks like it might try to execute code
    case "$LINE" in
      *'$('*|*'`'*)
        echo "var in .env may be executable, skipping"
        continue
        ;;
    esac

    echo "loading ${VAR} from .env"
    # strip quotes and carriage returns so values from quoted or
    # Windows-edited .env files export cleanly
    export "$(echo "$LINE" | tr -d "\r\"'")"
  done
else
  echo ".env vars don't exist."
  # webroot .env is symlinked to this path
  touch /pelican-data/.env

  # manually generate a key because key generate --force fails
  if [ -z "${APP_KEY}" ]; then
    echo "No key set, Generating key."
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    echo "APP_KEY=$APP_KEY" > /pelican-data/.env
    echo "Generated app key written to .env file"
  else
    echo "APP_KEY exists in environment, using that."
    echo "APP_KEY=${APP_KEY}" > /pelican-data/.env
  fi

  # enable installer
  echo "APP_INSTALLED=false" >> /pelican-data/.env
fi

# create directories for volumes
mkdir -p /pelican-data/database /pelican-data/storage/avatars /pelican-data/storage/fonts /pelican-data/storage/icons /pelican-data/plugins /var/www/html/storage/logs/supervisord 2>/dev/null

# if the app is installed then we need to run migrations on start. New installs will run migrations when you run the installer.
if [ "${APP_INSTALLED}" = "true" ];  then
  #if the db is anything but sqlite wait until it's accepting connections
  if [ "${DB_CONNECTION}" != "sqlite" ]; then
    # check for DB up before starting the panel
    echo "Checking database status."
    until nc -z -v -w30 "${DB_HOST}" "${DB_PORT}"
    do
      echo "Waiting for database connection..."
      # wait for 1 seconds before check again
      sleep 1
    done
  else
    echo "using sqlite database"
  fi
  
  # run migration, unless disabled (e.g. when running multiple replicas
  # against the same database)
  if [ "${SKIP_MIGRATIONS:-false}" = "true" ]; then
    echo "Skipping migrations (SKIP_MIGRATIONS=true)"
  else
    php artisan migrate --force
  fi

  php artisan p:plugin:composer
fi

echo "Optimizing Filament"
php artisan filament:optimize

# Note: config/route/event caches are intentionally NOT built here. Settings
# live in .env and are edited at runtime, and plugins register providers (and
# routes) at runtime - those caches would freeze them until the next restart.
echo "Caching Blade views"
php artisan view:cache

# default to caddy not starting
export SUPERVISORD_CADDY=false
export CADDY_APP_URL="${APP_URL}"

# checking if app url is https
if (echo "${APP_URL}" | grep -qE '^https://'); then
  # check lets encrypt email was set without a proxy
  if [ -z "${LE_EMAIL}" ] && [ "${BEHIND_PROXY}" != "true" ]; then
    echo "when app url is https a lets encrypt email must be set when not behind a proxy"
    exit 1
  fi
  echo "https domain found setting email var"
  export CADDY_LE_EMAIL="email ${LE_EMAIL}"
fi

# when running behind a proxy
if [ "${BEHIND_PROXY}" = "true" ]; then
  echo "running behind proxy"
  echo "listening on port 80 internally"
  export CADDY_LE_EMAIL=""
  export CADDY_APP_URL=":80"
  export CADDY_AUTO_HTTPS="auto_https off"
  export ASSET_URL="${APP_URL}"
fi

# disable caddy if SKIP_CADDY is set
if [ "${SKIP_CADDY:-}" = "true" ]; then
  echo "Starting PHP-FPM only"
else
  echo "Starting PHP-FPM and Caddy"
  # enable caddy
  export SUPERVISORD_CADDY=true

  # handle trusted proxies for caddy when variable has data
  if [ -n "${TRUSTED_PROXIES:-}" ]; then
    FORMATTED_PROXIES=$(echo "trusted_proxies static ${TRUSTED_PROXIES}" | sed 's/,/ /g')
    export CADDY_TRUSTED_PROXIES="${FORMATTED_PROXIES}"
    export CADDY_STRICT_PROXIES="trusted_proxies_strict"
  fi
fi

echo "Starting Supervisord"
exec "$@"