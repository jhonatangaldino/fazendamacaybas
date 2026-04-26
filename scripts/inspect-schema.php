<?php
echo "=== partners ===\n";
foreach (DB::select('describe partners') as $col) {
    echo $col->Field . " | " . $col->Type . "\n";
}
