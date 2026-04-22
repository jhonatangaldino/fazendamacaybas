#!/usr/bin/env bash
#
# Script executado UMA ÚNICA VEZ no servidor Hostinger via SSH,
# para preparar a estrutura de releases/shared antes do primeiro deploy automático.
#
# Uso:
#   1) Copie este arquivo para o servidor:
#      scp -P 65002 scripts/first-deploy.sh u931382046@147.93.14.208:~/first-deploy.sh
#
#   2) Conecte via SSH:
#      ssh -p 65002 u931382046@147.93.14.208
#
#   3) Execute:
#      bash ~/first-deploy.sh
#
# Depois de rodar, você ainda precisa colocar o .env real em shared/.env.

set -euo pipefail

DEPLOY_BASE="/home/u931382046/domains/fazendamacaybas.com.br"
KEEP_RELEASES=5

echo "==> Criando estrutura de diretórios em ${DEPLOY_BASE}"
mkdir -p "${DEPLOY_BASE}/releases"
mkdir -p "${DEPLOY_BASE}/artifacts"
mkdir -p "${DEPLOY_BASE}/shared/storage/app/public"
mkdir -p "${DEPLOY_BASE}/shared/storage/framework/cache/data"
mkdir -p "${DEPLOY_BASE}/shared/storage/framework/sessions"
mkdir -p "${DEPLOY_BASE}/shared/storage/framework/views"
mkdir -p "${DEPLOY_BASE}/shared/storage/logs"
mkdir -p "${DEPLOY_BASE}/shared/scripts"
mkdir -p "${DEPLOY_BASE}/backups"

chmod -R 775 "${DEPLOY_BASE}/shared/storage"

echo "==> Estrutura criada:"
ls -la "${DEPLOY_BASE}"

echo ""
echo "==> PRÓXIMO PASSO — colocar o .env de produção:"
echo "    1. No seu PC: cat .env.production | ssh -p 65002 u931382046@147.93.14.208 'cat > ${DEPLOY_BASE}/shared/.env'"
echo "    2. No servidor: chmod 600 ${DEPLOY_BASE}/shared/.env"
echo ""
echo "==> DEPOIS — copiar activate.sh para shared/scripts:"
echo "    scp -P 65002 scripts/activate.sh u931382046@147.93.14.208:${DEPLOY_BASE}/shared/scripts/activate.sh"
echo ""
echo "==> Quando o primeiro deploy do GitHub Actions rodar, ele criará releases/<timestamp>/"
echo "    e o activate.sh trocará o symlink public_html → releases/current/public."
