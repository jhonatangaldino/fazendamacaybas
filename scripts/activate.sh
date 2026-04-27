#!/usr/bin/env bash
#
# activate.sh — Roda no servidor Hostinger, chamado pelo GitHub Actions após upload.
#
# Recebe via env vars:
#   RELEASE_ID   — timestamp da release (ex: 20260422143500)
#   DEPLOY_BASE  — caminho base (/home/u.../domains/fazendamacaybas.com.br)
#
# O que faz:
#   1. Extrai o tarball em releases/<RELEASE_ID>/
#   2. Symlinka .env e storage da pasta shared/
#   3. Roda composer install (se necessário), migrations, caches
#   4. Troca o symlink releases/current atomicamente
#   5. Atualiza public_html → releases/current/public
#   6. Remove releases antigas (mantém últimas 5)

set -euo pipefail

RELEASE_ID="${RELEASE_ID:?RELEASE_ID não definido}"
DEPLOY_BASE="${DEPLOY_BASE:?DEPLOY_BASE não definido}"
KEEP_RELEASES=5

RELEASE_DIR="${DEPLOY_BASE}/releases/${RELEASE_ID}"
SHARED_DIR="${DEPLOY_BASE}/shared"
ARTIFACT="${DEPLOY_BASE}/artifacts/release-${RELEASE_ID}.tar.gz"

echo "==> Extraindo release ${RELEASE_ID}"
mkdir -p "${RELEASE_DIR}"
tar -xzf "${ARTIFACT}" -C "${RELEASE_DIR}"

cd "${RELEASE_DIR}"

# Se o tarball foi gerado sem vendor/ (caso do deploy-local.sh, que roda
# npm build local mas não tem composer), instala aqui no servidor. O
# pipeline GitHub Actions já embutia vendor/; neste caso o bloco vira no-op.
if [ ! -d vendor ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
    echo "==> vendor/ ausente — rodando composer install no servidor"
    composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader
fi

echo "==> Linkando .env compartilhado"
rm -f .env
ln -sf "${SHARED_DIR}/.env" .env

echo "==> Linkando storage compartilhado"
rm -rf storage
ln -sf "${SHARED_DIR}/storage" storage

echo "==> Garantindo permissões"
mkdir -p bootstrap/cache
chmod -R 775 bootstrap/cache

echo "==> Linkando public/storage para storage/app/public"
rm -rf public/storage
ln -sf "${SHARED_DIR}/storage/app/public" public/storage

# Reestruturação 2026-04-27: subdomínio app.fazendamacaybas.com.br aponta
# para public_html/sistema/. Como public_html é symlink → releases/current/public/,
# precisamos public_html/sistema/ resolver para a mesma pasta. Símlink auto-
# referência (sistema → .) faz public_html/sistema/index.php apontar para
# o próprio Laravel, com __DIR__ resolvendo corretamente no realpath.
echo "==> Garantindo symlink public/sistema → . (subdomínio app.*)"
rm -f public/sistema
ln -sfn . public/sistema

echo "==> Rodando migrations"
php artisan migrate --force --no-interaction

echo "==> Otimizando caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Se o banco estiver vazio (primeira subida), roda o seeder.
NEED_SEED=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 | tr -d '[:space:]' || echo "0")
if [ "${NEED_SEED}" = "0" ]; then
    echo "==> Banco vazio — rodando seeders iniciais"
    php artisan db:seed --force --no-interaction
fi

echo "==> Trocando symlink releases/current → ${RELEASE_ID}"
ln -sfn "${RELEASE_DIR}" "${DEPLOY_BASE}/releases/current"

# Na primeira vez, o public_html pode ser um diretório real; vira symlink.
if [ ! -L "${DEPLOY_BASE}/public_html" ] && [ -d "${DEPLOY_BASE}/public_html" ]; then
    echo "==> Primeiro deploy: movendo public_html → public_html.legacy"
    mv "${DEPLOY_BASE}/public_html" "${DEPLOY_BASE}/public_html.legacy"
fi
ln -sfn "${DEPLOY_BASE}/releases/current/public" "${DEPLOY_BASE}/public_html"

echo "==> Limpando releases antigas (mantendo últimas ${KEEP_RELEASES})"
cd "${DEPLOY_BASE}/releases"
ls -1t | grep -v '^current$' | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo "==> Limpando artefatos antigos"
cd "${DEPLOY_BASE}/artifacts"
ls -1t | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -f

echo "==> Touch para invalidar OPcache"
php -r "if (function_exists('opcache_reset')) opcache_reset(); echo 'opcache reset ok';" 2>/dev/null || true

echo "✅ Release ${RELEASE_ID} ativa."
