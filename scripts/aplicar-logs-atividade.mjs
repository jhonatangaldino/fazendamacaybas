// Aplica trait LogsAtividade em todos os models operacionais que ainda
// não têm log. Insere `use App\Traits\LogsAtividade;` no header e
// `use LogsAtividade;` na declaração de traits da classe.
import fs from 'node:fs';
import path from 'node:path';

const MODELS = [
    // Agrícola
    'app/Models/Agricultural/Crop.php',
    'app/Models/Agricultural/Field.php',
    'app/Models/Agricultural/FieldApplication.php',
    'app/Models/Agricultural/Harvest.php',
    'app/Models/Agricultural/Planting.php',
    'app/Models/Agricultural/Season.php',
    // Categoria & Custos
    'app/Models/Category.php',
    'app/Models/CostCenter.php',
    // Documentos
    'app/Models/Document/Document.php',
    // Funcionários / Tarefas
    'app/Models/Employee.php',
    'app/Models/Task/Task.php',
    'app/Models/Task/Checklist.php',
    'app/Models/Task/ChecklistItem.php',
    'app/Models/Task/TaskAssignment.php',
    // Farm
    'app/Models/Farm.php',
    // Financeiro
    'app/Models/Financial/FinancialAccount.php',
    'app/Models/Financial/FinancialRecurrence.php',
    // Rebanho
    'app/Models/Livestock/AnimalEvent.php',
    'app/Models/Livestock/AnimalLocation.php',
    'app/Models/Livestock/AnimalLot.php',
    // Parceiros
    'app/Models/Partner.php',
    // Estoque
    'app/Models/Stock/StockItem.php',
    'app/Models/Stock/StockMovement.php',
    'app/Models/Stock/Warehouse.php',
    // Máquinas
    'app/Models/Vehicle/Vehicle.php',
    'app/Models/Vehicle/VehicleEvent.php',
    'app/Models/Vehicle/MaintenanceOrder.php',
];

let aplicados = 0;
let pulados = 0;

for (const file of MODELS) {
    if (! fs.existsSync(file)) {
        console.log(`  ⚠️ ${file} · arquivo não existe`);
        continue;
    }
    let src = fs.readFileSync(file, 'utf8');

    // Skip se já tem LogsActivity ou LogsAtividade
    if (/use\s+LogsActivity\b|use\s+LogsAtividade\b/.test(src)) {
        console.log(`  ⊘ ${file} · já tem log`);
        pulados++;
        continue;
    }

    // 1. Adiciona import logo após o último `use ...;` antes da classe
    const importToAdd = "use App\\Traits\\LogsAtividade;";
    if (! src.includes(importToAdd)) {
        // Insere logo após o último use namespace antes do bloco "class"
        const useImports = [...src.matchAll(/^use\s+[^;]+;$/gm)];
        if (useImports.length > 0) {
            const lastUse = useImports[useImports.length - 1];
            const insertAt = lastUse.index + lastUse[0].length;
            src = src.slice(0, insertAt) + "\n" + importToAdd + src.slice(insertAt);
        }
    }

    // 2. Adiciona "use LogsAtividade;" dentro da classe
    // Tenta inserir depois de "class XXX extends Model {" ou de outro `use` existente
    const classMatch = src.match(/class\s+\w+\s+extends\s+\w+(?:\s+implements\s+[^{]+)?\s*\{/);
    if (! classMatch) {
        console.log(`  ⚠️ ${file} · não achou declaração de classe`);
        continue;
    }

    // Busca primeira linha de `use XXX;` dentro do corpo da classe
    const classBody = src.slice(classMatch.index + classMatch[0].length);
    const traitUseMatch = classBody.match(/\n\s+use\s+([^;]+);/);

    if (traitUseMatch) {
        // Já tem traits — adiciona LogsAtividade na lista
        const existingTraits = traitUseMatch[1];
        const newTraits = existingTraits.includes('LogsAtividade')
            ? existingTraits
            : `${existingTraits.trim()}, LogsAtividade`;
        src = src.replace(`use ${existingTraits};`, `use ${newTraits};`);
    } else {
        // Sem traits — insere logo depois de "{"
        const insertPoint = classMatch.index + classMatch[0].length;
        src = src.slice(0, insertPoint) + "\n    use LogsAtividade;\n" + src.slice(insertPoint);
    }

    fs.writeFileSync(file, src);
    console.log(`  ✅ ${file}`);
    aplicados++;
}

console.log(`\n✅ ${aplicados} models · ${pulados} já tinham`);
