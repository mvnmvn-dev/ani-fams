<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sql = file_get_contents(__DIR__ . '/patches/ani_facilities_content.sql');
$lines = preg_split('/\r?\n/', $sql);
$buf = '';
$statements = [];
foreach ($lines as $line) {
    if (preg_match('/^\s*--/', $line)) {
        continue;
    }
    $buf .= $line . "\n";
    if (str_contains($line, ';')) {
        $stmt = trim($buf);
        if ($stmt !== '' && $stmt !== ';') {
            $statements[] = $stmt;
        }
        $buf = '';
    }
}

$ok = 0;
$fail = 0;
foreach ($statements as $stmt) {
    try {
        Illuminate\Support\Facades\DB::unprepared($stmt);
        $ok++;
    } catch (Throwable $e) {
        $fail++;
        echo 'FAIL: ' . substr($e->getMessage(), 0, 240) . PHP_EOL;
    }
}

echo "Applied ok={$ok} fail={$fail}" . PHP_EOL;

$facilities = Illuminate\Support\Facades\DB::table('facilities')->orderBy('id')->get(['name', 'type', 'location', 'capacity']);
foreach ($facilities as $f) {
    echo "{$f->name} | {$f->type} | {$f->location} | cap {$f->capacity}" . PHP_EOL;
}

$areas = Illuminate\Support\Facades\DB::table('visit_areas')->orderBy('id')->pluck('name');
echo 'Visit areas: ' . $areas->implode(', ') . PHP_EOL;

$equipment = Illuminate\Support\Facades\DB::table('equipment_items')->orderBy('id')->pluck('name');
echo 'Equipment: ' . $equipment->implode(', ') . PHP_EOL;
