<?php

namespace common\components\Home\Service;

use Yii;

/**
 * Orquestador del panel de inicio (web site/index + apps móviles staff).
 */
final class HomePanelService
{
    private HomePanelManifest $manifest;
    private HomePanelSectionRegistry $registry;

    public function __construct(?HomePanelManifest $manifest = null, ?HomePanelSectionRegistry $registry = null)
    {
        $this->manifest = $manifest ?? new HomePanelManifest();
        $this->registry = $registry ?? new HomePanelSectionRegistry();
    }

    /**
     * @param array<string, mixed> $options fecha, sections (list<string>), prueba, id_efector
     * @return array<string, mixed>
     */
    public function buildPanel(array $options = []): array
    {
        $encounterClass = Yii::$app->user->getEncounterClass();
        $fecha = isset($options['fecha']) ? (string) $options['fecha'] : date('Y-m-d');
        $filterSectionIds = $this->normalizeSectionFilter($options['sections'] ?? null);

        $panelDef = $this->manifest->resolveForStaff($encounterClass ? (string) $encounterClass : null);
        $context = [
            'fecha' => $fecha,
            'prueba' => !empty($options['prueba']),
            'id_efector' => isset($options['id_efector']) ? (int) $options['id_efector'] : null,
        ];

        $sections = [];
        foreach ($panelDef['sections'] as $def) {
            $sectionId = $def['id'];
            if ($filterSectionIds !== null && !in_array($sectionId, $filterSectionIds, true)) {
                continue;
            }

            $provider = $this->registry->get($def['provider']);
            if ($provider === null) {
                continue;
            }

            try {
                $data = $provider->build($context);
            } catch (\InvalidArgumentException $e) {
                if ($filterSectionIds !== null) {
                    throw $e;
                }
                continue;
            }

            $section = [
                'id' => $sectionId,
                'kind' => $def['kind'],
                'data' => $data,
            ];
            if (isset($def['poll_interval_seconds'])) {
                $section['poll_interval_seconds'] = (int) $def['poll_interval_seconds'];
            }
            $sections[] = $section;
        }

        if ($sections === [] && $encounterClass) {
            throw new \InvalidArgumentException('No hay secciones disponibles para el contexto operativo actual.');
        }

        if ($sections === []) {
            $fallback = $this->manifest->resolveForStaff(null);
            foreach ($fallback['sections'] as $def) {
                if ($filterSectionIds !== null && !in_array($def['id'], $filterSectionIds, true)) {
                    continue;
                }
                $provider = $this->registry->get($def['provider']);
                if ($provider === null) {
                    continue;
                }
                $sections[] = [
                    'id' => $def['id'],
                    'kind' => $def['kind'],
                    'data' => $provider->build($context),
                ];
            }
            $panelDef = $fallback;
        }

        return [
            'layout' => $panelDef['layout'],
            'title' => $panelDef['title'],
            'encounter_class' => $encounterClass ?: null,
            'fecha' => $fecha,
            'sections' => $sections,
        ];
    }

    /**
     * @param mixed $raw
     * @return list<string>|null
     */
    private function normalizeSectionFilter($raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $parts = array_filter(array_map('trim', explode(',', $raw)));
            return $parts === [] ? null : array_values($parts);
        }
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $item) {
                $s = trim((string) $item);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
            return $out === [] ? null : $out;
        }

        return null;
    }
}
