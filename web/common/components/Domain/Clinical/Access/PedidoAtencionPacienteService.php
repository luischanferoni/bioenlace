<?php

namespace common\components\Domain\Clinical\Access;

/**
 * Pedido de atención desde el hub paciente (acto → línea agendable).
 */
final class PedidoAtencionPacienteService
{
    public const TRIAGE_RAIZ_ESTUDIO = 'estudio_pedido';

    public const DRAFT_ACTO = 'pedido_acto';
    public const DRAFT_MODO = 'pedido_modo';
    public const DRAFT_LINEA_IDS = 'pedido_linea_ids';
    public const DRAFT_SERVICIO_RESUELTO = 'pedido_servicio_resuelto';
    public const DRAFT_MENSAJE = 'pedido_mensaje';

    private PedidoAtencionService $resolver;
    private LineaActoCatalogInterface $catalog;

    public function __construct(
        ?PedidoAtencionService $resolver = null,
        ?LineaActoCatalogInterface $catalog = null
    ) {
        $this->catalog = $catalog ?? CompositeLineaActoCatalog::defaultCatalog();
        $this->resolver = $resolver ?? new PedidoAtencionService($this->catalog);
    }

    /**
     * Actos con al menos una línea que acepta turnos (para UI paciente).
     *
     * @return list<array{code: string, label: string, urgency_band: null, halts_booking: bool}>
     */
    public function opcionesActoParaTriagePaso(): array
    {
        $out = [];
        foreach ($this->actosReservables() as $acto) {
            $out[] = [
                'code' => $acto['system'] . '|' . $acto['code'],
                'label' => $acto['display'] !== '' ? $acto['display'] : $acto['code'],
                'urgency_band' => null,
                'halts_booking' => false,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{code: string, system: string, display: string}>
     */
    public function actosReservables(): array
    {
        if ($this->usesDatabaseCatalog()) {
            return $this->actosReservablesDesdeDb();
        }

        return $this->catalog->listActos();
    }

    private function usesDatabaseCatalog(): bool
    {
        return $this->catalog instanceof DbLineaActoCatalog
            || $this->catalog instanceof CompositeLineaActoCatalog;
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function esPedidoEstudio(array $draft): bool
    {
        return trim((string) ($draft['triage_raiz'] ?? '')) === self::TRIAGE_RAIZ_ESTUDIO
            || trim((string) ($draft[self::DRAFT_ACTO] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $draft
     * @return list<int>
     */
    public function lineaIdsDesdeDraft(array $draft): array
    {
        $raw = trim((string) ($draft[self::DRAFT_LINEA_IDS] ?? ''));
        if ($raw === '') {
            return [];
        }
        $ids = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
            if (is_numeric($part) && (int) $part > 0) {
                $ids[] = (int) $part;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resuelve acto del draft → líneas / servicio asignado.
     *
     * @param array<string, mixed> $draft
     */
    public function aplicarFlagsEnDraft(array &$draft): void
    {
        if (!$this->esPedidoEstudio($draft)) {
            return;
        }

        if (trim((string) ($draft[self::DRAFT_MODO] ?? '')) === '') {
            $draft[self::DRAFT_MODO] = PedidoAtencion::MODO_ESTUDIO;
        }

        $parsed = self::parseActoValue(trim((string) ($draft[self::DRAFT_ACTO] ?? '')));
        if ($parsed === null) {
            unset($draft[self::DRAFT_LINEA_IDS], $draft[self::DRAFT_SERVICIO_RESUELTO], $draft[self::DRAFT_MENSAJE]);

            return;
        }

        $modo = trim((string) $draft[self::DRAFT_MODO]);
        $efectorId = isset($draft['id_efector']) && is_numeric($draft['id_efector'])
            ? (int) $draft['id_efector']
            : null;

        $pedido = new PedidoAtencion(
            null,
            $parsed['code'],
            $parsed['system'],
            $modo !== '' ? $modo : PedidoAtencion::MODO_ESTUDIO,
            null,
            $efectorId,
            $parsed['display'] ?? null
        );
        $resolved = $this->resolver->resolve($pedido);
        $candidatas = $resolved['candidates']['lineas'];
        if ($resolved['complete'] && $resolved['pedido']->hasLinea()) {
            $candidatas = [[
                'id' => (int) $resolved['pedido']->lineaId,
                'label' => '',
                'preferente' => true,
            ]];
        }

        // Solo líneas con agenda (acepta_turnos).
        $candidatas = $this->filtrarLineasConAgenda($candidatas);
        $ids = array_map(static fn (array $l) => (int) $l['id'], $candidatas);
        $draft[self::DRAFT_LINEA_IDS] = implode(',', $ids);

        if ($ids === []) {
            $draft[self::DRAFT_SERVICIO_RESUELTO] = '0';
            $draft[self::DRAFT_MENSAJE] = 'No hay agenda disponible para ese estudio en este momento.';
            unset($draft['id_servicio_asignado']);

            return;
        }

        unset($draft[self::DRAFT_MENSAJE]);
        if (count($ids) === 1) {
            $draft['id_servicio_asignado'] = (string) $ids[0];
            $draft[self::DRAFT_SERVICIO_RESUELTO] = '1';
        } else {
            $draft[self::DRAFT_SERVICIO_RESUELTO] = '0';
            // Varias líneas: el paciente elige en select_servicio (filtrado).
            if (isset($draft['id_servicio_asignado'])
                && !in_array((int) $draft['id_servicio_asignado'], $ids, true)
            ) {
                unset($draft['id_servicio_asignado']);
            }
        }
    }

    /**
     * @return array{code: string, system: string, display?: string}|null
     */
    public static function parseActoValue(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, '|')) {
            [$system, $code] = explode('|', $raw, 2);
            $system = trim($system);
            $code = trim($code);
            if ($system === '' || $code === '' || !CodingSystems::isAllowed($system, PedidoAtencionMetadata::allowedSystems())) {
                return null;
            }

            return ['code' => $code, 'system' => $system];
        }
        if (!CodingSystems::isAllowed(CodingSystems::SNOMED, PedidoAtencionMetadata::allowedSystems())) {
            return null;
        }

        return ['code' => $raw, 'system' => CodingSystems::SNOMED];
    }

    /**
     * @param list<array{id: int, label: string, preferente?: bool}> $lineas
     * @return list<array{id: int, label: string, preferente?: bool}>
     */
    private function filtrarLineasConAgenda(array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }
        if (!$this->usesDatabaseCatalog()) {
            return $lineas;
        }

        $ids = array_map(static fn (array $l) => (int) $l['id'], $lineas);
        $q = \common\models\Servicio::find()
            ->select(['id_servicio'])
            ->where(['id_servicio' => $ids, 'acepta_turnos' => 'SI']);
        \common\models\Servicio::applyOfertaInstitucionalScope($q);
        $ok = $q->column();
        $okSet = array_fill_keys(array_map('intval', $ok), true);

        return array_values(array_filter(
            $lineas,
            static fn (array $l) => isset($okSet[(int) $l['id']])
        ));
    }

    /**
     * @return list<array{code: string, system: string, display: string}>
     */
    private function actosReservablesDesdeDb(): array
    {
        $q = (new \yii\db\Query())
            ->from(['a' => \common\models\Clinical\ActoClinico::tableName()])
            ->innerJoin(['la' => \common\models\Clinical\LineaActo::tableName()], 'la.id_acto = a.id')
            ->innerJoin(['s' => \common\models\Servicio::tableName()], 's.id_servicio = la.id_servicio')
            ->select(['a.code', 'a.code_system', 'a.display', 'a.fhir_category'])
            ->where(['s.acepta_turnos' => 'SI'])
            ->andWhere(['not in', 'a.fhir_category', ['consultation', 'referral']]);
        \common\models\Servicio::applyOfertaInstitucionalScope($q, 's');
        $rows = $q->distinct()->orderBy(['a.display' => SORT_ASC])->all();

        $out = [];
        foreach ($rows as $row) {
            $system = (string) $row['code_system'];
            if (!CodingSystems::isAllowed($system, PedidoAtencionMetadata::allowedSystems())) {
                continue;
            }
            $out[] = [
                'code' => (string) $row['code'],
                'system' => $system,
                'display' => (string) $row['display'],
            ];
        }

        return $out;
    }
}
