// Fix dos imports BelongsToFarm em 22 models — corrige o "AppDomainTenancyTraitsBelongsToFarm"
// que ficou sem backslashes por bug de escape do bash.
import fs from 'node:fs';
import path from 'node:path';

const files = [
  'app/Models/Agricultural/Field.php','app/Models/Agricultural/FieldApplication.php',
  'app/Models/Agricultural/Harvest.php','app/Models/Agricultural/Planting.php',
  'app/Models/BarcodeLookup.php','app/Models/Document/Document.php',
  'app/Models/Employee.php','app/Models/Financial/FinancialAccount.php',
  'app/Models/Financial/FinancialRecurrence.php','app/Models/Financial/FinancialTransaction.php',
  'app/Models/Livestock/Animal.php','app/Models/Livestock/AnimalEvent.php',
  'app/Models/Livestock/AnimalLocation.php','app/Models/Livestock/AnimalLot.php',
  'app/Models/Stock/StockItem.php','app/Models/Stock/StockMovement.php',
  'app/Models/Stock/Warehouse.php','app/Models/Task/Checklist.php',
  'app/Models/Task/Task.php','app/Models/Vehicle/MaintenanceOrder.php',
  'app/Models/Vehicle/Vehicle.php','app/Models/Vehicle/VehicleEvent.php',
];

// String com 4 backslashes (cada \\ no source é 1 \ na string)
const broken = 'use AppDomainTenancyTraitsBelongsToFarm;';
const correct = 'use App\\Domain\\Tenancy\\Traits\\BelongsToFarm;';

console.log('Correct length:', correct.length, '(should be 41 if has 4 backslashes)');

let fixed = 0;
for (const f of files) {
  let src = fs.readFileSync(f, 'utf8');
  if (src.includes(broken)) {
    src = src.replaceAll(broken, correct);
    fs.writeFileSync(f, src);
    fixed++;
  }
}
console.log(`Fixed ${fixed}/${files.length} files`);
