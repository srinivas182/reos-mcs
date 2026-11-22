#!/bin/sh
# One image, four roles: web (Octane on FrankenPHP), worker, scheduler, reverb.
set -e
cd /app

case "${1:-web}" in
  web)
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000 --workers="${OCTANE_WORKERS:-4}" --max-requests=500
    ;;
  worker)
    exec php artisan queue:work --queue="${QUEUES:-default,billing,connections,content}" --tries=3 --max-time=3600
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  reverb)
    exec php artisan reverb:start --host=0.0.0.0 --port=8080
    ;;
  *)
    exec "$@"
    ;;
esac
