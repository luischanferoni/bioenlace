<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../common/config/bootstrap.php';
require __DIR__ . '/../console/config/bootstrap.php';

$config = require __DIR__ . '/../console/config/main.php';
$app = new yii\console\Application($config);

$all = common\components\Assistant\Catalog\YamlIntentCatalogService::discoverAll(false);
$items = [];
$byId = [];
foreach ($all as $a) {
    $actionId = isset($a['action_id']) ? (string) $a['action_id'] : '';
    if ($actionId === '') {
        continue;
    }
    $display = (string) ($a['action_name'] ?? $a['display_name'] ?? '');
    $desc = (string) ($a['description'] ?? '');
    $entity = isset($a['entity']) ? (string) $a['entity'] : null;
    $route = (string) ($a['route'] ?? '');
    $kw = [];
    foreach (['keywords', 'synonyms', 'tags'] as $k) {
        if (isset($a[$k]) && is_array($a[$k])) {
            foreach ($a[$k] as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $kw[] = trim($v);
                }
            }
        }
    }
    $kw = array_values(array_unique($kw));
    $params = ['expected' => $a['parameters'] ?? [], 'provided' => []];
    $item = new common\components\Assistant\IntentEngine\UiActionCatalogItem(
        $actionId,
        $display !== '' ? $display : $actionId,
        $desc,
        $entity !== '' ? $entity : null,
        $route,
        $kw,
        $params
    );
    $items[] = $item;
    $byId[$actionId] = $item;
}
$rc = new ReflectionClass(common\components\Assistant\IntentEngine\UiActionCatalog::class);
$ctor = $rc->getConstructor();
$ctor->setAccessible(true);
/** @var common\components\Assistant\IntentEngine\UiActionCatalog $catalog */
$catalog = $rc->newInstanceWithoutConstructor();
$ctor->invoke($catalog, $items, $byId);

$msg = 'necesito cargar la agenda para un medico';
$res = common\components\Assistant\IntentEngine\IntentClassifier::classify($msg, $catalog);
echo "CLASSIFY (rules/ai):\n";
echo json_encode($res ? [
    'action_id' => $res['item']->action_id,
    'display_name' => $res['item']->display_name,
    'confidence' => $res['confidence'],
    'method' => $res['method'],
] : null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

echo "\nPROCESS_QUERY (IntentEngine):\n";
echo json_encode(common\components\Assistant\IntentEngine\IntentEngine::processQuery($msg, 0, null), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

echo "\nCATALOG_ITEM agenda.crear-rrhh-flow:\n";
echo json_encode($all[array_search('agenda.crear-rrhh-flow', array_column($all, 'action_id'), true)] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

