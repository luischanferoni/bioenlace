<?php

namespace common\components\Ui\Home\Service;

use common\components\Core\Product\ProductMetadataPaths;
use common\models\Clinical\Encounter;
use common\models\Servicio;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Resuelve layout y secciones del panel desde home_panel_manifest.yaml.
 */
final class HomePanelManifest
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{
     *   layout: string,
     *   title: string,
     *   sections: list<array{id: string, provider: string, kind: string, poll_interval_seconds?: int}>
     * }
     */
    public function resolve(string $audience, ?string $encounterClass): array
    {
        $manifest = $this->load();
        if ($audience === HomePanelAudienceResolver::PATIENT) {
            return $this->normalizePanel($manifest['panels']['patient'] ?? $manifest['panels']['fallback'] ?? []);
        }

        if ($audience === HomePanelAudienceResolver::STAFF) {
            return $this->resolveForStaff($encounterClass);
        }

        return $this->normalizePanel($manifest['panels']['fallback'] ?? []);
    }

    /**
     * @return array{
     *   layout: string,
     *   title: string,
     *   sections: list<array{id: string, provider: string, kind: string, poll_interval_seconds?: int}>
     * }
     */
    public function resolveForStaff(?string $encounterClass): array
    {
        $manifest = $this->load();
        if ($encounterClass === null || $encounterClass === '') {
            return $this->normalizePanel($manifest['panels']['fallback'] ?? []);
        }

        $staff = $manifest['panels']['staff'] ?? [];
        if (!isset($staff[$encounterClass])) {
            return $this->normalizePanel($manifest['panels']['fallback'] ?? []);
        }

        $panel = $staff[$encounterClass];
        if ($encounterClass === Encounter::ENCOUNTER_CLASS_IMP) {
            $idServicio = (int) Yii::$app->user->getServicioActual();
            $key = ($idServicio > 0 && Servicio::esServicioAgendaQuirurgica($idServicio))
                ? 'imp_surgical'
                : 'imp_floor';
            $panel = [
                'layout' => $panel['layout'] ?? 'clinical_list',
                'title' => $panel['title'] ?? 'Internación',
                'sections' => $panel[$key]['sections'] ?? [],
            ];
        }

        return $this->normalizePanel($panel);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = ProductMetadataPaths::homePanelManifestFile();
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }
        self::$cache = Yaml::parseFile($path);

        return self::$cache;
    }

    /**
     * @param array<string, mixed> $panel
     * @return array{layout: string, title: string, sections: list<array{id: string, provider: string, kind: string, poll_interval_seconds?: int}>}
     */
    private function normalizePanel(array $panel): array
    {
        $sections = [];
        foreach (($panel['sections'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (string) $row['id'] : '';
            $provider = isset($row['provider']) ? (string) $row['provider'] : '';
            $kind = isset($row['kind']) ? (string) $row['kind'] : $provider;
            if ($id === '' || $provider === '') {
                continue;
            }
            $section = [
                'id' => $id,
                'provider' => $provider,
                'kind' => $kind,
            ];
            if (isset($row['poll_interval_seconds'])) {
                $section['poll_interval_seconds'] = (int) $row['poll_interval_seconds'];
            }
            $sections[] = $section;
        }

        return [
            'layout' => (string) ($panel['layout'] ?? 'cards'),
            'title' => (string) ($panel['title'] ?? 'Inicio'),
            'sections' => $sections,
        ];
    }
}
