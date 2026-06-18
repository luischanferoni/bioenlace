<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Textos de capacitación para opt-in remoto en agenda ({@see metadata/agenda_atencion_remota.yaml}).
 */
final class AgendaAtencionRemotaCatalogService
{
    private const CATALOG_FILE = 'agenda_atencion_remota.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function mensajeInfoConfigurarAgenda(): string
    {
        $block = self::load()['configurar_agenda'] ?? [];

        return trim((string) (is_array($block) ? ($block['info_message'] ?? '') : ''));
    }

    /**
     * @return array{label: string, hint: string}
     */
    public function campoAceptaConsultasOnline(): array
    {
        $block = self::load()['configurar_agenda']['acepta_consultas_online'] ?? [];

        return [
            'label' => trim((string) (is_array($block) ? ($block['label'] ?? '') : '')),
            'hint' => trim((string) (is_array($block) ? ($block['hint'] ?? '') : '')),
        ];
    }

    /**
     * @return array{action_id: string, link_label: string, assistant_url_path: string}
     */
    public function insightAgendaConfig(): array
    {
        $block = self::load()['insight_agenda_config'] ?? [];
        if (!is_array($block)) {
            return ['action_id' => '', 'link_label' => '', 'assistant_url_path' => ''];
        }

        return [
            'action_id' => trim((string) ($block['action_id'] ?? '')),
            'link_label' => trim((string) ($block['link_label'] ?? '')),
            'assistant_url_path' => trim((string) ($block['assistant_url_path'] ?? '')),
        ];
    }

    /**
     * @return array{label: string, periodo_dias: int, elegibilidad: string}
     */
    public function kpiPresencialRemoto(): array
    {
        $block = self::load()['kpi'] ?? [];

        return [
            'label' => trim((string) (is_array($block) ? ($block['label'] ?? 'Presencial (remoto posible)') : 'Presencial (remoto posible)')),
            'periodo_dias' => max(1, (int) (is_array($block) ? ($block['periodo_dias'] ?? 30) : 30)),
            'elegibilidad' => trim((string) (is_array($block) ? ($block['elegibilidad'] ?? 'sugerido') : 'sugerido')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = __DIR__ . '/../metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }

        $parsed = Yaml::parseFile($path);
        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }

    /** Solo tests. */
    public static function resetCache(): void
    {
        self::$cache = null;
    }
}
