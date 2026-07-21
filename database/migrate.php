<?php

require __DIR__ . '/../config/Database.php';

$fresh = in_array('--fresh', $argv, true);
$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$pdo = Database::connect();

try {
    if ($fresh) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (array_reverse($files) as $file) {
            if (preg_match('/create_(\w+)_table\.sql$/', $file, $matches)) {
                $table = $matches[1];
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                echo "Dropped table: {$table}\n";
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    foreach ($files as $file) {
        $pdo->exec(file_get_contents($file));
        echo 'Migrated: ' . basename($file) . "\n";
    }

    echo "Migration" . ($fresh ? ':fresh' : '') . " complete.\n";
} catch (\Throwable $e) {
    echo 'FAILED: ' . $e->getMessage() . "\n";
    exit(1);
}
