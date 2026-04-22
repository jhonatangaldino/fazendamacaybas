#!/usr/bin/env bash
#
# backup-db.sh — Backup diário do MySQL via cron.
# Cron sugerido:
#   0 3 * * * /home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/backup-db.sh

set -euo pipefail

DEPLOY_BASE="/home/u931382046/domains/fazendamacaybas.com.br"
BACKUP_DIR="${DEPLOY_BASE}/backups"
KEEP_DAYS=14
STAMP=$(date +%Y-%m-%d_%H%M%S)

# Lê credenciais do .env
set -a
source "${DEPLOY_BASE}/shared/.env"
set +a

mkdir -p "${BACKUP_DIR}"

FILE="${BACKUP_DIR}/macaybas-${STAMP}.sql.gz"
mysqldump -h "${DB_HOST}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" \
    --single-transaction --routines --triggers --events \
    | gzip > "${FILE}"

# Remove backups antigos
find "${BACKUP_DIR}" -name 'macaybas-*.sql.gz' -mtime +${KEEP_DAYS} -delete

echo "Backup criado: ${FILE}"
