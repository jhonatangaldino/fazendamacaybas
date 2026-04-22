#!/usr/bin/env bash
#
# rollback.sh — Aponta o symlink "current" para a release anterior.
# Uso no servidor:
#   bash ~/domains/fazendamacaybas.com.br/shared/scripts/rollback.sh
#   bash ~/domains/fazendamacaybas.com.br/shared/scripts/rollback.sh <release_id>

set -euo pipefail

DEPLOY_BASE="/home/u931382046/domains/fazendamacaybas.com.br"
RELEASES_DIR="${DEPLOY_BASE}/releases"

if [ -n "${1:-}" ]; then
    TARGET="$1"
else
    # Pega a release anterior à atual
    CURRENT=$(readlink "${RELEASES_DIR}/current" | xargs basename)
    TARGET=$(ls -1t "${RELEASES_DIR}" | grep -v '^current$' | grep -v "^${CURRENT}\$" | head -1)
fi

if [ ! -d "${RELEASES_DIR}/${TARGET}" ]; then
    echo "❌ Release ${TARGET} não encontrada."
    echo "Releases disponíveis:"
    ls -1t "${RELEASES_DIR}" | grep -v '^current$'
    exit 1
fi

echo "==> Apontando current → ${TARGET}"
ln -sfn "${RELEASES_DIR}/${TARGET}" "${RELEASES_DIR}/current"
ln -sfn "${RELEASES_DIR}/current/public" "${DEPLOY_BASE}/public_html"

cd "${RELEASES_DIR}/current"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php -r "if (function_exists('opcache_reset')) opcache_reset();" 2>/dev/null || true

echo "✅ Rollback concluído. Release ativa: ${TARGET}"
