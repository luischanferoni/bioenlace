<?php

namespace common\components\Domain\Scheduling\BehaviorProfile\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class TurnoAgentActionExplanationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function unavailableMessage(): string
    {
        $message = self::load()['unavailable_message'] ?? null;
        if (!is_string($message) || trim($message) === '') {
            throw new \RuntimeException('Falta el mensaje para acción de turno no disponible.');
        }

        return trim($message);
    }

    /**
     * @return array{disclaimer: string, action_label: string, explanation_text: string}
     */
    public function forAction(string $actionCode, bool $snapshotSuperseded): array
    {
        $config = self::load();
        $copy = $config['actions'][$actionCode] ?? $config['actions']['default'] ?? null;
        if (!is_array($copy)) {
            throw new \RuntimeException('Falta copy de explicación para la acción de turno: ' . $actionCode);
        }

        foreach (['label', 'explanation'] as $field) {
            if (!is_string($copy[$field] ?? null) || trim($copy[$field]) === '') {
                throw new \RuntimeException(sprintf('Copy de explicación de turno inválido: %s/%s.', $actionCode, $field));
            }
        }
        foreach (['disclaimer', 'superseded_snapshot_suffix'] as $field) {
            if (!is_string($config[$field] ?? null) || trim($config[$field]) === '') {
                throw new \RuntimeException('Copy general de explicación de turno inválido: ' . $field);
            }
        }

        return [
            'disclaimer' => trim($config['disclaimer']),
            'action_label' => trim($copy['label']),
            'explanation_text' => trim($copy['explanation'])
                . ($snapshotSuperseded ? ' ' . trim($config['superseded_snapshot_suffix']) : ''),
        ];
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'BehaviorProfile'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'turno-action-explanations.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de explicaciones de acciones de turno: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['actions'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo turno-action-explanations inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
