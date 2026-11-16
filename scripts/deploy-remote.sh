#!/usr/bin/env bash
# Aktivace nového vydání na Hostingeru (spouští GitHub Actions přes SSH).
#   $1 = DEPLOY_PATH (např. ~/domains/staging.gratulomat.cz), $2 = název vydání
# Struktura:
#   releases/<vydání>/    rozbalený kód s vendor/
#   shared/.env           konfigurace (ručně, jednou)
#   shared/storage/       data: média, logy, cache, sessions
#   current -> releases/<vydání>
#   public_html -> current/public   (ručně, jednou – viz docs/DEPLOY.md)
set -euo pipefail
BASE="$1"; REL="$2"; PHP="${PHP_BIN:-php}"
cd "$BASE"
mkdir -p releases shared/storage/{app/media,framework/{cache/data,sessions,views},logs}
[ -f shared/.env ] || { echo "Chybí $BASE/shared/.env"; exit 1; }

mkdir -p "releases/$REL"
tar -xzf release.tgz -C "releases/$REL"
rm -f release.tgz
ln -sfn "$BASE/shared/.env" "releases/$REL/.env"
rm -rf "releases/$REL/storage"
ln -sfn "$BASE/shared/storage" "releases/$REL/storage"

cd "releases/$REL"
"$PHP" artisan migrate --force
"$PHP" artisan optimize
cd "$BASE"

ln -sfn "releases/$REL" current.new && mv -Tf current.new current
"$PHP" current/artisan queue:restart || true

# ponechat posledních 5 vydání
ls -1dt releases/*/ | tail -n +6 | xargs -r rm -rf
echo "Aktivní vydání: $REL"
