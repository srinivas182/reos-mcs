#!/usr/bin/env bash
# Wipes the preview back to the journey personas. Pilot users are removed:
# run bin/pilots.sh again afterwards. Asks before doing anything.
set -euo pipefail
cd "$(dirname "$0")/.."
read -r -p "This deletes everything pilot users did in the preview. Type RESET to continue: " answer
[ "$answer" = "RESET" ] || { echo "Nothing changed."; exit 1; }
docker compose exec -T web php artisan migrate:fresh --seed --force
docker compose exec -T web php artisan cache:clear
echo "The preview is back to the demo personas."
