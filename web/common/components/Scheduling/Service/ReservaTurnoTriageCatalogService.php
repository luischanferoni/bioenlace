<?php

namespace common\components\Scheduling\Service;

use common\models\ReservaTriageTeleconsultaElegibilidad;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Catálogo declarativo de triage clínico al reservar turno ({@see metadata/reserva_triage_catalog_v1.yaml}).
 *
 * Pasos de flow-only (p. ej. modalidad) viven en servicios dedicados ({@see ReservaTriageModalidadStepService}).
 */
final class ReservaTurnoTriageCatalogService
{
    private const CATALOG_FILE = 'reserva_triage_catalog_v1.yaml';

    /** @var array<string, array{title: string, draft_field: string}> */
    private const FLOW_ONLY_STEPS = [
        ReservaTriageModalidadStepService::STEP_ID => [
            'title' => ReservaTriageModalidadStepService::TITLE,
            'draft_field' => ReservaTriageModalidadStepService::DRAFT_FIELD,
        ],
    ];

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public function getManifest(): array
    {
        return self::load();
    }

    public function getVersion(): string
    {
        $m = self::load();

        return isset($m['version']) ? (string) $m['version'] : '1';
    }

    public function getHaltMessageBandA(): string
    {
        $m = self::load();

        return trim((string) ($m['halt_message_band_a'] ?? ''));
    }

    /**
     * @return array{title: string, draft_field: string}|null
     */
    public function getStep(string $stepId): ?array
    {
        $stepId = trim($stepId);
        if ($stepId === '') {
            return null;
        }
        $steps = self::load()['steps'] ?? [];
        if (!is_array($steps) || !isset($steps[$stepId]) || !is_array($steps[$stepId])) {
            $flowOnly = self::FLOW_ONLY_STEPS[$stepId] ?? null;

            return $flowOnly;
        }
        $s = $steps[$stepId];
        $title = trim((string) ($s['title'] ?? ''));
        $draftField = trim((string) ($s['draft_field'] ?? ''));
        if ($title === '' || $draftField === '') {
            return null;
        }

        return ['title' => $title, 'draft_field' => $draftField];
    }

    /**
     * @return list<string>
     */
    public function listStepIds(): array
    {
        $steps = self::load()['steps'] ?? [];
        if (!is_array($steps)) {
            return [];
        }

        return array_values(array_unique(array_merge(
            array_map('strval', array_keys($steps)),
            array_keys(self::FLOW_ONLY_STEPS)
        )));
    }

    public function findNode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        foreach ($this->allNodes() as $node) {
            if (($node['code'] ?? '') === $code) {
                return $node;
            }
        }

        return null;
    }

    public function nodeHaltsBooking(string $code): bool
    {
        $node = $this->findNode($code);

        return $node !== null && !empty($node['halts_booking']);
    }

    /**
     * @return list<array{code: string, label: string, urgency_band: string|null, halts_booking: bool}>
     */
    public function getOptionsForStep(string $stepId, ?string $parentCode = null): array
    {
        $stepId = trim($stepId);
        $parentCode = $parentCode !== null ? trim($parentCode) : null;
        $out = [];
        foreach ($this->allNodes() as $node) {
            if (($node['step'] ?? '') !== $stepId) {
                continue;
            }
            $parent = isset($node['parent']) ? trim((string) $node['parent']) : '';
            if ($parentCode !== null && $parentCode !== '') {
                if ($parent !== $parentCode) {
                    continue;
                }
            } elseif ($parent !== '') {
                continue;
            }
            $code = trim((string) ($node['code'] ?? ''));
            $label = trim((string) ($node['label'] ?? ''));
            if ($code === '' || $label === '') {
                continue;
            }
            $out[] = [
                'code' => $code,
                'label' => $label,
                'urgency_band' => isset($node['urgency_band']) ? trim((string) $node['urgency_band']) : null,
                'halts_booking' => !empty($node['halts_booking']),
            ];
        }

        return $out;
    }

    /**
     * Arma meta persistible y banda máxima a partir de códigos elegidos.
     *
     * @param array<string, mixed> $selections mapa draft_field o clave lógica => código
     * @return array{
     *   reserva_triage_code: string,
     *   urgency_band: string,
     *   reserva_triage_halt: bool,
     *   reserva_triage_meta_json: array<string, mixed>,
     *   suggests_tipo_atencion: string|null
     * }
     */
    public function compileSelections(array $selections): array
    {
        $selections = $this->normalizeSelections($selections);
        $path = [];
        $bandOrder = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1];
        $maxBand = 'D';
        $halt = false;
        $leafCode = '';
        $suggestTipo = null;

        $orderedKeys = [
            'triage_raiz',
            'triage_alarma_gate',
            'triage_alarmas',
            'triage_zona',
            'triage_detalle',
            'triage_evolucion',
        ];
        foreach ($orderedKeys as $key) {
            if (!isset($selections[$key])) {
                continue;
            }
            $code = trim((string) $selections[$key]);
            if ($code === '') {
                continue;
            }
            $node = $this->findNode($code);
            $path[] = [
                'field' => $key,
                'code' => $code,
                'label' => $node['label'] ?? $code,
            ];
            $leafCode = $code;
            if ($node !== null) {
                if (!empty($node['halts_booking'])) {
                    $halt = true;
                }
                $b = isset($node['urgency_band']) ? trim((string) $node['urgency_band']) : '';
                if ($b !== '' && ($bandOrder[$b] ?? 0) > ($bandOrder[$maxBand] ?? 0)) {
                    $maxBand = $b;
                }
            }
        }

        $codigos = [];
        $skipElegibilidad = ['alarma_gate_no', 'alarma_gate_si', 'alarma_ninguna'];
        foreach ($orderedKeys as $key) {
            if (!isset($selections[$key])) {
                continue;
            }
            $code = trim((string) $selections[$key]);
            if ($code !== '' && !in_array($code, $skipElegibilidad, true)) {
                $codigos[] = $code;
            }
        }
        $suggestTipo = ReservaTriageTeleconsultaElegibilidad::suggestTipoAtencionParaCodigos($codigos);

        $nota = isset($selections['triage_nota']) ? trim((string) $selections['triage_nota']) : '';
        if ($nota !== '') {
            $path[] = ['field' => 'triage_nota', 'code' => '_free_text', 'label' => $nota];
        }

        if ($leafCode === '' && isset($selections['triage_raiz'])) {
            $leafCode = trim((string) $selections['triage_raiz']);
        }

        return [
            'reserva_triage_code' => $leafCode,
            'urgency_band' => $maxBand,
            'reserva_triage_halt' => $halt,
            'reserva_triage_meta_json' => [
                'catalog_version' => $this->getVersion(),
                'path' => $path,
                'compiled_at' => gmdate('c'),
            ],
            'suggests_tipo_atencion' => $suggestTipo,
        ];
    }

    /**
     * @param array<string, mixed> $selections
     */
    public function assertCanPersistBooking(array $selections): void
    {
        $selections = $this->normalizeSelections($selections);
        $compiled = $this->compileSelections($selections);
        if ($compiled['reserva_triage_halt']) {
            throw new \InvalidArgumentException($this->getHaltMessageBandA());
        }
        $raiz = trim((string) ($selections['triage_raiz'] ?? ''));
        if ($raiz === '') {
            throw new \InvalidArgumentException('Indicá por qué necesitás el turno antes de confirmar.');
        }
        if ($this->findNode($raiz) === null) {
            throw new \InvalidArgumentException('Motivo de solicitud no válido.');
        }
        if ($raiz === 'sintoma_nuevo') {
            foreach (['triage_alarma_gate', 'triage_zona', 'triage_detalle'] as $req) {
                if (trim((string) ($selections[$req] ?? '')) === '') {
                    throw new \InvalidArgumentException('Completá las preguntas sobre tu malestar antes de confirmar el turno.');
                }
            }
            if (trim((string) ($selections['triage_alarmas'] ?? '')) === '') {
                throw new \InvalidArgumentException('Completá las preguntas sobre tu malestar antes de confirmar el turno.');
            }

            return;
        }
        if ($raiz === 'control_cronico') {
            foreach (['triage_alarma_gate', 'triage_evolucion'] as $req) {
                if (trim((string) ($selections[$req] ?? '')) === '') {
                    throw new \InvalidArgumentException('Completá las preguntas de seguridad y evolución antes de confirmar el turno.');
                }
            }
            if (trim((string) ($selections['triage_alarmas'] ?? '')) === '') {
                throw new \InvalidArgumentException('Completá las preguntas de seguridad antes de confirmar el turno.');
            }
        }
    }

    /**
     * @param array<string, mixed> $selections
     * @return array<string, mixed>
     */
    public function normalizeSelections(array $selections): array
    {
        $gate = trim((string) ($selections['triage_alarma_gate'] ?? ''));
        if ($gate === 'alarma_gate_no' && trim((string) ($selections['triage_alarmas'] ?? '')) === '') {
            $selections['triage_alarmas'] = 'alarma_ninguna';
        }

        return $selections;
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allNodes(): array
    {
        $nodes = self::load()['nodes'] ?? [];

        return is_array($nodes) ? $nodes : [];
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
            throw new \RuntimeException('Catálogo de triage no encontrado: ' . $path);
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo de triage inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
