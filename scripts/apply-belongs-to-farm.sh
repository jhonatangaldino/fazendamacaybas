#!/bin/bash
# Aplica BelongsToFarm trait em massa nos 22 models operacionais.
# Idempotente — pula arquivos que já têm o trait aplicado.

set -e
cd "$(dirname "$0")/.."

FILES=(
  "app/Models/Agricultural/Field.php"
  "app/Models/Agricultural/FieldApplication.php"
  "app/Models/Agricultural/Harvest.php"
  "app/Models/Agricultural/Planting.php"
  "app/Models/BarcodeLookup.php"
  "app/Models/Document/Document.php"
  "app/Models/Employee.php"
  "app/Models/Financial/FinancialAccount.php"
  "app/Models/Financial/FinancialRecurrence.php"
  "app/Models/Financial/FinancialTransaction.php"
  "app/Models/Livestock/Animal.php"
  "app/Models/Livestock/AnimalEvent.php"
  "app/Models/Livestock/AnimalLocation.php"
  "app/Models/Livestock/AnimalLot.php"
  "app/Models/Stock/StockItem.php"
  "app/Models/Stock/StockMovement.php"
  "app/Models/Stock/Warehouse.php"
  "app/Models/Task/Checklist.php"
  "app/Models/Task/Task.php"
  "app/Models/Vehicle/MaintenanceOrder.php"
  "app/Models/Vehicle/Vehicle.php"
  "app/Models/Vehicle/VehicleEvent.php"
)

count_modified=0
count_skipped=0

for f in "${FILES[@]}"; do
  if [[ ! -f "$f" ]]; then
    echo "  ⚠ não existe: $f"
    continue
  fi
  if grep -q "BelongsToFarm" "$f"; then
    count_skipped=$((count_skipped + 1))
    continue
  fi

  # 1. Adiciona import logo após o `use BelongsToTenant`
  if grep -q "use App\\\\Domain\\\\Tenancy\\\\Traits\\\\BelongsToTenant;" "$f"; then
    # Adiciona import abaixo (sed busca a linha do BelongsToTenant import e insere outro logo após)
    sed -i.bak '/^use App\\\\Domain\\\\Tenancy\\\\Traits\\\\BelongsToTenant;/a use App\\Domain\\Tenancy\\Traits\\BelongsToFarm;' "$f"
  fi

  # 2. Adiciona uso do trait dentro da classe (procurando 'use BelongsToTenant;' como linha)
  sed -i.bak2 's/use BelongsToTenant;/use BelongsToTenant, BelongsToFarm;/' "$f"

  rm -f "$f.bak" "$f.bak2"
  count_modified=$((count_modified + 1))
  echo "  ✓ aplicado em $f"
done

echo ""
echo "═══ RESUMO ═══"
echo "Modificados: $count_modified"
echo "Pulados (já tinham): $count_skipped"
