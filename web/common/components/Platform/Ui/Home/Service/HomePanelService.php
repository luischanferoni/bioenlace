<?php

namespace common\components\Platform\Ui\Home\Service;

use Yii;

/**
 * Orquestador del panel de inicio (web site/index + apps móviles).
 */
final class HomePanelService
{
    private HomePanelManifest $manifest;
    private HomePanelSectionRegistry $registry;
    private HomePanelAudienceResolver $audienceResolver;

    public function __construct(
        ?HomePanelManifest $manifest = null,
        ?HomePanelSectionRegistry $registry = null,
        ?HomePanelAudienceResolver $audienceResolver = null
    ) {
        $this->manifest = $manifest ?? new HomePanelManifest();
        $this->registry = $registry ?? new HomePanelSectionRegistry();
        $this->audienceResolver = $audienceResolver ?? new HomePanelAudienceResolver();
    }

    /**
     * @param array<string, mixed> $options fecha, sections, prueba, id_efector, subject_persona_id
     * @return array<string, mixed>
     */
    public function buildPanel(array $options = []): array
    {
        $encounterClass = Yii::$app->user->getEncounterClass();
        $fecha = isset($options['fecha']) ? (string) $options['fecha'] : date('Y-m-d');
        $filterSectionIds = $this->normalizeSectionFilter($options['sections'] ?? null);
        $audience = $this->audienceResolver->resolve();

        $panelDef = $this->manifest->resolve(
            $audience,
            $encounterClass ? (string) $encounterClass : null
        );
        $context = [
            'fecha' => $fecha,
            'prueba' => !empty($options['prueba']),
            'id_efector' => isset($options['id_efector']) ? (int) $options['id_efector'] : null,
            'subject_persona_id' => isset($options['subject_persona_id'])
                ? (int) $options['subject_persona_id']
                : null,
        ];

        $sections = $this->buildSections($panelDef['sections'], $context, $filterSectionIds, true);

        if ($sections === [] && $audience === HomePanelAudienceResolver::STAFF && $encounterClass) {
            throw new \InvalidArgumentException('No hay secciones disponibles para el contexto operativo actual.');
        }

        if ($sections === [] && $audience !== HomePanelAudienceResolver::FALLBACK) {
            $fallback = $this->manifest->resolve(HomePanelAudienceResolver::FALLBACK, null);
            $sections = $this->buildSections($fallback['sections'], $context, $filterSectionIds, false);
            $panelDef = $fallback;
        }

        return [
            'layout' => $panelDef['layout'],
            'title' => $panelDef['title'],
            'audience' => $audience,
            'encounter_class' => $encounterClass ?: null,
            'fecha' => $fecha,
            'sections' => $sections,
        ];
    }

    /**
     * @param list<array{id: string, provider: string, kind: string, poll_interval_seconds?: int}> $definitions
     * @param list<string>|null $filterSectionIds
     * @return list<array<string, mixed>>
     */
    private function buildSections(array $definitions, array $context, ?array $filterSectionIds, bool $throwOnProviderError): array
    {
        $sections = [];
        foreach ($definitions as $def) {
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
                if ($throwOnProviderError && $filterSectionIds !== null) {
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

        return $sections;
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
