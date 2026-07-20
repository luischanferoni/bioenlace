<?php

namespace common\components\Domain\Clinical\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo definitional de protocolos de cuidado (PlanDefinition-lite).
 *
 * @see metadata/care_protocols.yaml
 */
final class CareProtocolCatalogService
{
    private const CATALOG_FILE = 'care_protocols.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return list<array{
     *   id: string,
     *   title: string,
     *   hub_label: string,
     *   fhir_kind: string,
     *   enabled: bool,
     *   applies: array{
     *     condition_codes: list<string>,
     *     clinical_status: list<string>,
     *     age_years: array{min: int|null, max: int|null}|null,
     *     sex: list<string>
     *   },
     *   actions: list<array{code: string, label: string, description: string, outcome: string, draft: array<string, string>}>
     * }>
     */
    public function allProtocols(): array
    {
        $out = [];
        foreach (self::load()['protocols'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $enabled = !array_key_exists('enabled', $row) || (bool) $row['enabled'];
            $applies = is_array($row['applies'] ?? null) ? $row['applies'] : [];
            $codes = [];
            foreach ($applies['condition_codes'] ?? [] as $c) {
                $s = strtoupper(trim((string) $c));
                if ($s !== '') {
                    $codes[] = $s;
                }
            }
            $statuses = [];
            foreach ($applies['clinical_status'] ?? [] as $st) {
                $s = strtolower(trim((string) $st));
                if ($s !== '') {
                    $statuses[] = $s;
                }
            }
            $sex = [];
            foreach ($applies['sex'] ?? [] as $sx) {
                $s = strtoupper(trim((string) $sx));
                if ($s !== '') {
                    $sex[] = $s;
                }
            }
            $ageYears = null;
            if (isset($applies['age_years']) && is_array($applies['age_years'])) {
                $min = $applies['age_years']['min'] ?? null;
                $max = $applies['age_years']['max'] ?? null;
                $ageYears = [
                    'min' => $min !== null && $min !== '' ? (int) $min : null,
                    'max' => $max !== null && $max !== '' ? (int) $max : null,
                ];
            }
            $actions = [];
            foreach ($row['actions'] ?? [] as $action) {
                if (!is_array($action)) {
                    continue;
                }
                $code = trim((string) ($action['code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $draft = [];
                foreach ($action['draft'] ?? [] as $k => $v) {
                    $draft[trim((string) $k)] = trim((string) $v);
                }
                $outcome = trim((string) ($action['outcome'] ?? ''));
                if ($outcome === '') {
                    $outcome = $this->defaultOutcome();
                }
                $actions[] = [
                    'code' => $code,
                    'label' => trim((string) ($action['label'] ?? $code)),
                    'description' => trim((string) ($action['description'] ?? '')),
                    'outcome' => $outcome,
                    'draft' => $draft,
                ];
            }
            $title = trim((string) ($row['title'] ?? $id));
            $hubLabel = trim((string) ($row['hub_label'] ?? ''));
            $out[] = [
                'id' => $id,
                'title' => $title,
                'hub_label' => $hubLabel !== '' ? $hubLabel : $title,
                'fhir_kind' => trim((string) ($row['fhir_kind'] ?? 'PlanDefinition')),
                'enabled' => $enabled,
                'applies' => [
                    'condition_codes' => $codes,
                    'clinical_status' => $statuses,
                    'age_years' => $ageYears,
                    'sex' => $sex,
                ],
                'actions' => $actions,
            ];
        }

        return $out;
    }

    public function findById(string $protocolId): ?array
    {
        $protocolId = trim($protocolId);
        foreach ($this->allProtocols() as $p) {
            if ($p['id'] === $protocolId) {
                return $p;
            }
        }

        return null;
    }

    public function defaultOutcome(): string
    {
        $o = trim((string) (self::load()['default_outcome'] ?? 'captura_mensaje'));

        return $o !== '' ? $o : 'captura_mensaje';
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = dirname(__DIR__) . '/metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }
        $parsed = Yaml::parseFile($path);
        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }
}
