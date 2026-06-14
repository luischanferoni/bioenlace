<?php

/**
 * Añade `permission:` explícito a intents CRUD que aún lo infieren.
 * Uso: php console/scripts/rbac_phase5_add_permissions.php [--dry-run]
 */

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../common/components/Assistant/Catalog/IntentSchemaPaths.php';
require_once __DIR__ . '/../../common/components/Core/Permission/IntentPermissionResolver.php';

use common\components\Assistant\Catalog\IntentSchemaPaths;
use common\components\Core\Permission\IntentPermissionResolver;
use Symfony\Component\Yaml\Yaml;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$base = IntentSchemaPaths::baseDir();
$updated = 0;

foreach (['create', 'read', 'update', 'delete'] as $cat) {
    foreach (glob($base . '/' . $cat . '/*.yaml') ?: [] as $path) {
        $raw = (string) file_get_contents($path);
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            continue;
        }
        $intentId = trim((string) ($data['intent_id'] ?? basename($path, '.yaml')));
        if (trim((string) ($data['permission'] ?? '')) !== '') {
            continue;
        }
        $permission = IntentPermissionResolver::resolve($intentId, $data);
        if ($permission === '' || strncmp($permission, '/api/', 5) === 0) {
            fwrite(STDERR, "Skip (sin permiso lógico): {$intentId}\n");
            continue;
        }

        if (preg_match('/^permission\s*:/m', $raw)) {
            continue;
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw);
        $insertAt = 0;
        foreach ($lines as $i => $line) {
            if (preg_match('/^intent_id\s*:/', $line)) {
                $insertAt = $i + 1;
                break;
            }
        }
        array_splice($lines, $insertAt, 0, ['permission: ' . $permission]);
        $newRaw = implode("\n", $lines);
        if (!$dryRun) {
            file_put_contents($path, $newRaw);
        }
        echo ($dryRun ? '[dry] ' : '') . basename($path) . ' => ' . $permission . "\n";
        $updated++;
    }
}

echo "Actualizados: {$updated}\n";
