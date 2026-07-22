<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Service\CarePlanPresentationService;
use common\components\Domain\Clinical\Service\CareProtocolMatcherService;
use common\components\Domain\Clinical\Service\ConditionPresentationService;
use common\components\Domain\Clinical\Service\PatientActiveCarePlanQuery;
use common\components\Domain\Person\Service\PacienteContextoService;
use common\models\Person\Persona;
use Symfony\Component\Yaml\Yaml;

/**
 * Arma el hub Control/Seguimiento: tratamientos, condiciones y controles recomendados.
 */
final class ControlSeguimientoHubService
{
    private const CATALOG_FILE = 'control_seguimiento_hub.yaml';

    public const ANCHOR_PREFIX_CARE_PLAN = 'cp:';

    public const ANCHOR_PREFIX_CONDITION = 'diag:';

    public const ANCHOR_PREFIX_PROTOCOL = 'prot:';

    public const ANCHOR_GENERAL = 'general';

    public const ANCHOR_CONSULTA_GENERAL = 'intake:consulta_general';

    public const ANCHOR_CONSULTA_PREVIA = 'intake:consulta_previa';

    public const KIND_CARE_PLAN = 'care_plan';

    public const KIND_CONDITION = 'condition';

    public const KIND_PROTOCOL = 'protocol';

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
                    'label' => $this->hubLabel('care_plan', [
                        'name' => (string) ($pick['label'] ?? $pick['name'] ?? $this->hubLabelRaw('care_plan_fallback_name', 'Plan')),
                    ]),
                    'subtitle' => $this->hubCarePlanSubtitle($pick, $plan),
                    'meta' => [
                        'kind' => self::KIND_CARE_PLAN,
                        'care_plan_id' => $planId,
                    ],
                ];
            }

            foreach ($this->listConditionHubItems($idPersona) as $condItem) {
                $items[] = $condItem;
            }

            $idProvincia = $this->resolveIdProvinciaContexto($idPersona);
            $profile = $this->resolvePersonaProfile($idPersona);
            foreach ((new CareProtocolMatcherService())->matchByProfile(
                $profile['age_years'],
                $profile['sex'],
                $idProvincia
            ) as $protocol) {
                $items[] = [
                    'id' => self::ANCHOR_PREFIX_PROTOCOL . $protocol['id'],
                    'label' => (string) ($protocol['hub_label'] ?? $protocol['title'] ?? $protocol['id']),
                    'subtitle' => $this->hubLabelRaw('protocol_profile_subtitle', ''),
                    'meta' => [
                        'kind' => self::KIND_PROTOCOL,
                        'protocol_id' => $protocol['id'],
                        'source' => 'profile',
                    ],
                ];
            }
        }

        return $items;
    }

    public function hubTitle(): string
    {
        return trim((string) (self::load()['hub']['title'] ?? ''));
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
        // Solo sintetizar ancla desde care_plan_id cuando ya hay camino explícito
        // (p. ej. deep-link desde detalle del plan con necesidad). Si no, el hub
        // «¿Sobre qué?» debe listarse aunque exista un único CarePlan activo.
        if (
            $anchor === ''
            && (int) ($draft['care_plan_id'] ?? 0) > 0
            && trim((string) ($draft['seguimiento_necesidad'] ?? '')) !== ''
        ) {
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
            $idPersona = (int) ($draft['id_persona'] ?? 0);
            $idProvincia = $idPersona > 0 ? $this->resolveIdProvinciaContexto($idPersona) : null;
            $protocol = (new CareProtocolMatcherService())
                ->matchByConditionCode($ref, $idProvincia, null, $idPersona > 0 ? $idPersona : null);
            if ($protocol !== null) {
                $draft['protocol_id'] = $protocol['id'];
            }

            return;
        }

        if (str_starts_with($anchor, self::ANCHOR_PREFIX_PROTOCOL)) {
            $protocolId = substr($anchor, strlen(self::ANCHOR_PREFIX_PROTOCOL));
            $draft['protocol_id'] = $protocolId;
            $draft['control_hub_kind'] = self::KIND_PROTOCOL;
            $draft['triage_raiz'] = 'seguimiento_cronico';

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
     * Acciones para condición o protocolo de perfil.
     *
     * @return list<array{id: string, label: string, subtitle: string, meta: array<string, mixed>}>
     */
    public function listConditionActionItems(?string $conditionCodigo = null, ?string $protocolId = null): array
    {
        $protocolId = trim((string) $protocolId);
        if ($protocolId !== '') {
            $protocolActions = (new CareProtocolMatcherService())->actionsForProtocolId($protocolId);
            if ($protocolActions !== []) {
                return $this->mapProtocolActionsToItems($protocolActions);
            }
        }

        $codigo = trim((string) $conditionCodigo);
        if ($codigo !== '') {
            $protocolActions = (new CareProtocolMatcherService())
                ->actionsForConditionCode($codigo);
            if ($protocolActions !== []) {
                return $this->mapProtocolActionsToItems($protocolActions);
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
     * @param list<array{code: string, label: string, description: string, outcome: string, draft: array<string, string>, protocol_id: string, protocol_title: string}> $protocolActions
     * @return list<array{id: string, label: string, subtitle: string, meta: array<string, mixed>}>
     */
    private function mapProtocolActionsToItems(array $protocolActions): array
    {
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

    /**
     * Resuelve outcome + draft de una acción (protocolo por id/código o default del hub).
     *
     * @return array{outcome: string, draft: array<string, string>, protocol_id: string}|null
     */
    public function resolveConditionAction(
        ?string $conditionCodigo,
        string $actionCode,
        ?string $protocolId = null
    ): ?array {
        $actionCode = trim($actionCode);
        if ($actionCode === '') {
            return null;
        }
        $matcher = new CareProtocolMatcherService();
        $protocolId = trim((string) $protocolId);
        if ($protocolId !== '') {
            $found = $matcher->findAction($protocolId, $actionCode);
            if ($found !== null) {
                return [
                    'outcome' => $found['outcome'],
                    'draft' => $found['draft'],
                    'protocol_id' => $found['protocol_id'],
                ];
            }
        }
        $codigo = trim((string) $conditionCodigo);
        if ($codigo !== '') {
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
     * @return array{age_years: int|null, sex: string|null}
     */
    private function resolvePersonaProfile(int $idPersona): array
    {
        $persona = Persona::findOne($idPersona);
        if ($persona === null) {
            return ['age_years' => null, 'sex' => null];
        }
        $age = null;
        try {
            $raw = $persona->getEdad();
            if (is_numeric($raw)) {
                $age = (int) $raw;
            } elseif (is_string($raw) && preg_match('/(\d+)/', $raw, $m)) {
                $age = (int) $m[1];
            }
        } catch (\Throwable $e) {
            $age = null;
        }
        $sex = null;
        try {
            if (method_exists($persona, 'getSexoLetra')) {
                $sex = strtoupper(trim((string) $persona->getSexoLetra()));
            }
        } catch (\Throwable $e) {
            $sex = null;
        }
        if ($sex === '') {
            $sex = null;
        }

        return ['age_years' => $age, 'sex' => $sex];
    }

    private function resolveIdProvinciaContexto(int $idPersona): ?int
    {
        if ($idPersona <= 0) {
            return null;
        }
        try {
            $ctx = (new PacienteContextoService())->getOrCreate($idPersona);
            $id = $ctx->id_provincia_contexto !== null ? (int) $ctx->id_provincia_contexto : 0;

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Condiciones activas del paciente para el hub (misma dedupe que home).
     *
     * @return list<array{id: string, label: string, subtitle: string, meta: array<string, mixed>}>
     */
    private function listConditionHubItems(int $idPersona): array
    {
        $out = [];
        foreach ((new ConditionPresentationService())->listPatientSummaries($idPersona) as $summary) {
            $code = trim((string) ($summary['codigo'] ?? ''));
            $labelText = trim((string) ($summary['label'] ?? ''));
            $protocolTitle = trim((string) ($summary['protocol_title'] ?? ''));
            $subtitle = $this->hubLabelRaw('condition_active', 'Activa');
            if ($protocolTitle !== '') {
                $subtitle = $this->hubLabel('condition_protocol', ['title' => $protocolTitle]);
            } elseif ($code !== '' && (bool) preg_match('/^[A-Za-z]/', $code)) {
                $subtitle = $code;
            }
            $out[] = [
                'id' => (string) ($summary['control_hub_anchor']
                    ?? (self::ANCHOR_PREFIX_CONDITION . ($code !== '' ? $code : (string) ($summary['id'] ?? '')))),
                'label' => $this->hubLabel('condition', ['name' => $labelText !== '' ? $labelText : 'Condición']),
                'subtitle' => $subtitle,
                'meta' => [
                    'kind' => self::KIND_CONDITION,
                    'condition_ref' => (string) ($summary['id'] ?? ''),
                    'codigo' => $code,
                ],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $pick
     * @param object $plan
     */
    private function hubCarePlanSubtitle(array $pick, object $plan): string
    {
        $periodStart = trim((string) ($pick['meta']['period_start'] ?? $plan->period_start ?? ''));
        if ($periodStart !== '') {
            $ts = strtotime($periodStart);
            if ($ts !== false) {
                return $this->hubLabel('care_plan_since', ['date' => date('d/m/Y', $ts)]);
            }
        }
        $category = trim((string) ($pick['meta']['category'] ?? ''));

        return $category !== '' ? $category : $this->hubLabelRaw('care_plan_active', 'Plan activo');
    }

    /**
     * @param array<string, string> $vars
     */
    private function hubLabel(string $key, array $vars = []): string
    {
        $tpl = $this->hubLabelRaw($key, '');
        if ($tpl === '') {
            return $vars['name'] ?? $vars['title'] ?? $vars['date'] ?? '';
        }
        $out = $tpl;
        foreach ($vars as $k => $v) {
            $out = str_replace('{' . $k . '}', $v, $out);
        }

        return $out;
    }

    private function hubLabelRaw(string $key, string $fallback): string
    {
        $labels = self::load()['hub']['labels'] ?? [];
        if (!is_array($labels)) {
            return $fallback;
        }
        $val = trim((string) ($labels[$key] ?? ''));

        return $val !== '' ? $val : $fallback;
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
