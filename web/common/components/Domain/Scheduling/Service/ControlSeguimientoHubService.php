<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Service\CarePlanPresentationService;
use common\components\Domain\Clinical\Service\CareProtocolMatcherService;
use common\components\Domain\Clinical\Service\PatientActiveCarePlanQuery;
use common\models\DiagnosticoConsultaRepository;
use Symfony\Component\Yaml\Yaml;

/**
 * Arma el hub Control/Seguimiento (anclas + fallback) desde metadata y dominio.
 */
final class ControlSeguimientoHubService
{
    private const CATALOG_FILE = 'control_seguimiento_hub.yaml';

    public const ANCHOR_PREFIX_CARE_PLAN = 'cp:';

    public const ANCHOR_PREFIX_CONDITION = 'diag:';

    public const ANCHOR_GENERAL = 'general';

    public const ANCHOR_CONSULTA_GENERAL = 'intake:consulta_general';

    public const ANCHOR_CONSULTA_PREVIA = 'intake:consulta_previa';

    public const KIND_CARE_PLAN = 'care_plan';

    public const KIND_CONDITION = 'condition';

    public const KIND_GENERAL = 'general';

    public const KIND_CONSULTA_GENERAL = 'consulta_general';

    public const KIND_CONSULTA_PREVIA = 'consulta_previa';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * Ítems para lista UI JSON (draft_field control_hub_anchor).
     *
     * @return list<array{id: string, label: string, subtitle: string, meta: array<string, mixed>}>
     */
    public function listHubItems(int $idPersona): array
    {
        $items = [];
        if ($idPersona > 0) {
            $presenter = new CarePlanPresentationService();
            foreach ((new PatientActiveCarePlanQuery())->listActive($idPersona) as $plan) {
                $pick = $presenter->toPatientListPickItem($plan);
                $planId = (int) ($plan->id ?? 0);
                if ($planId <= 0) {
                    continue;
                }
                $items[] = [
                    'id' => self::ANCHOR_PREFIX_CARE_PLAN . $planId,
                    'label' => 'Tratamiento: ' . (string) ($pick['label'] ?? $pick['name'] ?? 'Plan'),
                    'subtitle' => (string) ($pick['subtitle'] ?? ''),
                    'meta' => [
                        'kind' => self::KIND_CARE_PLAN,
                        'care_plan_id' => $planId,
                    ],
                ];
            }

            [$activas, $cronicas] = DiagnosticoConsultaRepository::getCondicionesPaciente($idPersona);
            $seenCodes = [];
            foreach (array_merge(array_values($cronicas), array_values($activas)) as $diag) {
                if (!is_object($diag)) {
                    continue;
                }
                $codigo = trim((string) ($diag->codigo ?? ''));
                $diagId = (string) ($diag->id ?? '');
                if ($diagId === '' && $codigo === '') {
                    continue;
                }
                $dedupe = $codigo !== '' ? $codigo : $diagId;
                if (isset($seenCodes[$dedupe])) {
                    continue;
                }
                $seenCodes[$dedupe] = true;
                $nombre = $this->conditionDisplayName($diag);
                $cronico = strtoupper(trim((string) ($diag->cronico ?? ''))) === 'SI';
                $items[] = [
                    'id' => self::ANCHOR_PREFIX_CONDITION . ($codigo !== '' ? $codigo : $diagId),
                    'label' => 'Condición: ' . $nombre,
                    'subtitle' => $cronico ? 'Crónica' : 'Activa',
                    'meta' => [
                        'kind' => self::KIND_CONDITION,
                        'condition_ref' => $diagId !== '' ? $diagId : $codigo,
                        'codigo' => $codigo,
                    ],
                ];
            }
        }

        $cfg = self::load();
        foreach ($cfg['hub']['extras'] ?? [] as $extra) {
            if (!is_array($extra)) {
                continue;
            }
            $code = trim((string) ($extra['code'] ?? ''));
            if ($code === 'consulta_general') {
                $items[] = [
                    'id' => self::ANCHOR_CONSULTA_GENERAL,
                    'label' => trim((string) ($extra['label'] ?? 'Consulta por mensaje')),
                    'subtitle' => trim((string) ($extra['description'] ?? '')),
                    'meta' => ['kind' => self::KIND_CONSULTA_GENERAL],
                ];
            } elseif ($code === 'consulta_previa') {
                $items[] = [
                    'id' => self::ANCHOR_CONSULTA_PREVIA,
                    'label' => trim((string) ($extra['label'] ?? 'Sobre una atención previa')),
                    'subtitle' => trim((string) ($extra['description'] ?? '')),
                    'meta' => ['kind' => self::KIND_CONSULTA_PREVIA],
                ];
            }
        }

        $fb = $cfg['hub']['fallback_general'] ?? [];
        $items[] = [
            'id' => self::ANCHOR_GENERAL,
            'label' => trim((string) ($fb['label'] ?? 'Pedir un control (turno)')),
            'subtitle' => trim((string) ($fb['description'] ?? '')),
            'meta' => ['kind' => self::KIND_GENERAL],
        ];

        return $items;
    }

    public function hubTitle(): string
    {
        $title = trim((string) (self::load()['hub']['title'] ?? ''));

        return $title !== '' ? $title : '¿Sobre qué es el control o seguimiento?';
    }

    /**
     * @return list<array{code: string, label: string, description: string, draft: array<string, string>}>
     */
    public function conditionDefaultActions(): array
    {
        $out = [];
        foreach (self::load()['condition_default_actions'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $draft = [];
            foreach ($row['draft'] ?? [] as $k => $v) {
                $draft[trim((string) $k)] = trim((string) $v);
            }
            $out[] = [
                'code' => $code,
                'label' => trim((string) ($row['label'] ?? $code)),
                'description' => trim((string) ($row['description'] ?? '')),
                'draft' => $draft,
            ];
        }

        return $out;
    }

    /**
     * Aplica selección del hub al draft (mutado).
     *
     * @param array<string, mixed> $draft
     */
    public function applyAnchorToDraft(array &$draft): void
    {
        $anchor = trim((string) ($draft['control_hub_anchor'] ?? ''));
        if ($anchor === '' && (int) ($draft['care_plan_id'] ?? 0) > 0) {
            $anchor = self::ANCHOR_PREFIX_CARE_PLAN . (int) $draft['care_plan_id'];
            $draft['control_hub_anchor'] = $anchor;
        }
        if ($anchor === '') {
            return;
        }

        if (str_starts_with($anchor, self::ANCHOR_PREFIX_CARE_PLAN)) {
            $id = (int) substr($anchor, strlen(self::ANCHOR_PREFIX_CARE_PLAN));
            if ($id > 0) {
                $draft['care_plan_id'] = (string) $id;
                $draft['intake_tipo'] = ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO;
                $draft['control_hub_kind'] = self::KIND_CARE_PLAN;
                $draft['triage_raiz'] = 'seguimiento_cronico';
            }

            return;
        }

        if (str_starts_with($anchor, self::ANCHOR_PREFIX_CONDITION)) {
            $ref = substr($anchor, strlen(self::ANCHOR_PREFIX_CONDITION));
            $draft['condition_ref'] = $ref;
            $draft['condition_codigo'] = $ref;
            $draft['control_hub_kind'] = self::KIND_CONDITION;
            $draft['triage_raiz'] = 'seguimiento_cronico';
            $protocol = (new CareProtocolMatcherService())
                ->matchByConditionCode($ref);
            if ($protocol !== null) {
                $draft['protocol_id'] = $protocol['id'];
            }

            return;
        }

        if ($anchor === self::ANCHOR_CONSULTA_GENERAL) {
            $draft['intake_tipo'] = ConsultasSeguimientoIntakeCatalogService::INTAKE_CONSULTA_GENERAL;
            $draft['control_hub_kind'] = self::KIND_CONSULTA_GENERAL;
            $draft['triage_raiz'] = 'seguimiento_cronico';

            return;
        }

        if ($anchor === self::ANCHOR_CONSULTA_PREVIA) {
            $draft['intake_tipo'] = ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA;
            $draft['control_hub_kind'] = self::KIND_CONSULTA_PREVIA;
            $draft['triage_raiz'] = 'seguimiento_cronico';

            return;
        }

        if ($anchor === self::ANCHOR_GENERAL) {
            $draft['control_hub_kind'] = self::KIND_GENERAL;
            $draft['triage_raiz'] = 'seguimiento_cronico';
        }
    }

    /**
     * Acciones para una condición: protocolo matched o fallback del hub.
     *
     * @return list<array{id: string, label: string, subtitle: string, meta: array<string, mixed>}>
     */
    public function listConditionActionItems(?string $conditionCodigo = null): array
    {
        $codigo = trim((string) $conditionCodigo);
        if ($codigo !== '') {
            $protocolActions = (new CareProtocolMatcherService())
                ->actionsForConditionCode($codigo);
            if ($protocolActions !== []) {
                $items = [];
                foreach ($protocolActions as $action) {
                    $items[] = [
                        'id' => $action['code'],
                        'label' => $action['label'],
                        'subtitle' => $action['description'] !== ''
                            ? $action['description']
                            : (string) ($action['protocol_title'] ?? ''),
                        'meta' => [
                            'draft' => $action['draft'],
                            'outcome' => $action['outcome'],
                            'protocol_id' => $action['protocol_id'],
                            'source' => 'protocol',
                        ],
                    ];
                }

                return $items;
            }
        }

        $items = [];
        foreach ($this->conditionDefaultActions() as $action) {
            $outcome = 'captura_mensaje';
            if (($action['code'] ?? '') === 'solicitar_turno') {
                $outcome = 'modalidad';
            }
            $items[] = [
                'id' => $action['code'],
                'label' => $action['label'],
                'subtitle' => $action['description'],
                'meta' => [
                    'draft' => $action['draft'],
                    'outcome' => $outcome,
                    'source' => 'default',
                ],
            ];
        }

        return $items;
    }

    /**
     * Resuelve outcome + draft de una acción de condición (protocolo o default).
     *
     * @return array{outcome: string, draft: array<string, string>, protocol_id: string}|null
     */
    public function resolveConditionAction(?string $conditionCodigo, string $actionCode): ?array
    {
        $actionCode = trim($actionCode);
        if ($actionCode === '') {
            return null;
        }
        $codigo = trim((string) $conditionCodigo);
        if ($codigo !== '') {
            $matcher = new CareProtocolMatcherService();
            $protocol = $matcher->matchByConditionCode($codigo);
            if ($protocol !== null) {
                foreach ($protocol['actions'] as $action) {
                    if ($action['code'] !== $actionCode) {
                        continue;
                    }

                    return [
                        'outcome' => $action['outcome'],
                        'draft' => $action['draft'],
                        'protocol_id' => $protocol['id'],
                    ];
                }
            }
        }
        foreach ($this->conditionDefaultActions() as $action) {
            if ($action['code'] !== $actionCode) {
                continue;
            }
            $outcome = $actionCode === 'solicitar_turno' ? 'modalidad' : 'captura_mensaje';

            return [
                'outcome' => $outcome,
                'draft' => $action['draft'],
                'protocol_id' => '',
            ];
        }

        return null;
    }

    /**
     * @param object $diag
     */
    private function conditionDisplayName(object $diag): string
    {
        if (isset($diag->codigoSnomed) && is_object($diag->codigoSnomed)) {
            $term = trim((string) ($diag->codigoSnomed->term ?? ''));
            if ($term !== '') {
                return $term;
            }
        }
        $diagText = trim((string) ($diag->diagnostico ?? ''));
        if ($diagText !== '') {
            return $diagText;
        }
        $codigo = trim((string) ($diag->codigo ?? ''));

        return $codigo !== '' ? $codigo : 'Diagnóstico';
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
