<?php

namespace console\controllers;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

/**
 * Validación del contrato de templates UI JSON bajo `frontend/modules/api/v1/views/json/` (recursivo).
 *
 * Corte total (sin retrocompatibilidad):
 * - Todo template debe declarar `ui_type`.
 * - `ui_meta.schema_version` y `ui_meta.clients` son obligatorios.
 * - `ui_type=ui_json` debe usar `blocks` (sin `wizard_config`, sin `steps`).
 */
class UiJsonTemplatesController extends Controller
{
    /**
     * Valida todos los templates y devuelve ExitCode::OK si pasan.
     */
    public function actionCheck(): int
    {
        $root = \Yii::getAlias('@frontend/modules/api/v1/views/json');
        $files = $this->collectJsonFiles($root);
        $errors = [];

        foreach ($files as $path) {
            $raw = @file_get_contents($path);
            if ($raw === false) {
                $errors[] = "No se pudo leer: {$path}";
                continue;
            }
            try {
                $decoded = Json::decode($raw);
            } catch (\Throwable $e) {
                $errors[] = "JSON inválido: {$path} ({$e->getMessage()})";
                continue;
            }
            if (!is_array($decoded)) {
                $errors[] = "JSON inválido (no objeto): {$path}";
                continue;
            }

            $uiType = isset($decoded['ui_type']) && is_string($decoded['ui_type']) ? trim($decoded['ui_type']) : '';
            if ($uiType === '') {
                $errors[] = "Falta ui_type: {$path}";
                continue;
            }
            if (!in_array($uiType, ['ui_json', 'flow'], true)) {
                $errors[] = "ui_type inválido ({$uiType}): {$path}";
                continue;
            }

            $meta = isset($decoded['ui_meta']) && is_array($decoded['ui_meta']) ? $decoded['ui_meta'] : null;
            if ($meta === null) {
                $errors[] = "Falta ui_meta: {$path}";
                continue;
            }
            if (!isset($meta['schema_version']) || !is_string($meta['schema_version']) || trim($meta['schema_version']) === '') {
                $errors[] = "Falta ui_meta.schema_version: {$path}";
            }
            if (!isset($meta['clients']) || !is_array($meta['clients']) || $meta['clients'] === []) {
                $errors[] = "Falta ui_meta.clients: {$path}";
            }

            if ($uiType === 'ui_json') {
                if (!isset($decoded['blocks']) || !is_array($decoded['blocks']) || $decoded['blocks'] === []) {
                    $errors[] = "ui_json sin blocks: {$path}";
                    continue;
                }
                if (isset($decoded['wizard_config']) || isset($decoded['steps'])) {
                    $errors[] = "ui_json legacy (wizard_config/steps) no permitido: {$path}";
                }
                foreach ($decoded['blocks'] as $idx => $b) {
                    if (!is_array($b)) {
                        $errors[] = "blocks[{$idx}] inválido (no objeto): {$path}";
                        continue;
                    }
                    $kind = isset($b['kind']) && is_string($b['kind']) ? trim($b['kind']) : '';
                    if ($kind === '') {
                        $errors[] = "blocks[{$idx}] sin kind: {$path}";
                        continue;
                    }
                    if (!in_array($kind, ['list', 'fields'], true)) {
                        $errors[] = "blocks[{$idx}] kind inválido ({$kind}): {$path}";
                        continue;
                    }
                    if ($kind === 'list') {
                        if (!array_key_exists('items', $b) || !is_array($b['items'])) {
                            $errors[] = "blocks[{$idx}] list sin items[]: {$path}";
                        }
                        if (!isset($b['draft_field']) || !is_string($b['draft_field']) || trim($b['draft_field']) === '') {
                            $errors[] = "blocks[{$idx}] list sin draft_field: {$path}";
                        }
                    }
                    if ($kind === 'fields') {
                        if (!isset($b['fields']) || !is_array($b['fields'])) {
                            $errors[] = "blocks[{$idx}] fields sin fields[]: {$path}";
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            foreach ($errors as $e) {
                $this->stderr($e . PHP_EOL);
            }
            $this->stderr('FAIL (' . count($errors) . " error/es)\n");
            return ExitCode::DATAERR;
        }

        $this->stdout('OK (' . count($files) . " templates)\n");
        return ExitCode::OK;
    }

    /**
     * @return list<string>
     */
    private function collectJsonFiles(string $root): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($it as $f) {
            if (!$f instanceof \SplFileInfo) {
                continue;
            }
            if (!$f->isFile()) {
                continue;
            }
            if (strtolower($f->getExtension()) !== 'json') {
                continue;
            }
            $out[] = str_replace('\\', '/', $f->getPathname());
        }
        sort($out);
        return $out;
    }
}

