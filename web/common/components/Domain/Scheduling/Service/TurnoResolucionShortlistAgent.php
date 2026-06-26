<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;
use Yii;

/**
 * Agente A01 v1 (D1): shortlist scoreado en push de reubicación.
 */
final class TurnoResolucionShortlistAgent
{
    public const AGENT_ID = 'turno-resolucion-shortlist';

    public const TRIGGER_TYPE = 'turno_en_resolucion';

    private TurnoResolucionShortlistService $shortlist;

    public function __construct(?TurnoResolucionShortlistService $shortlist = null)
    {
        $this->shortlist = $shortlist ?? new TurnoResolucionShortlistService();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildAndPersist(Turno $turno): array
    {
        if (!(Yii::$app->params['autonomous_agent_resolucion_shortlist_enabled'] ?? true)) {
            return [];
        }

        $res = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
        if ($res === null) {
            return [];
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return [];
        }

        $options = $this->shortlist->buildTopOptions($turno, $res, $config);
        if ($options === []) {
            return [];
        }

        $this->persistShortlist($res, $options);

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'shortlist_generated',
            (int) $res->id,
            null,
            (int) $turno->id_persona,
            null,
            [
                'id_turno' => (int) $turno->id_turnos,
                'option_count' => count($options),
            ],
            ['options' => $options]
        );

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function applyOption(Turno $turno, int $idPersona, string $optionId): array
    {
        if ($idPersona <= 0 || (int) $turno->id_persona !== $idPersona) {
            throw new \InvalidArgumentException('Turno no pertenece al paciente.');
        }

        $res = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
        if ($res === null) {
            throw new \InvalidArgumentException('No hay resolución pendiente.');
        }

        $option = $this->findStoredOption($res, $optionId);
        if ($option === null) {
            throw new \InvalidArgumentException('Opción de shortlist no encontrada o expirada.');
        }

        if (($option['kind'] ?? '') === 'neighbor' && !empty($option['eleccion'])) {
            $result = TurnoResolucionService::resolverEleccionVecina(
                (int) $turno->id_turnos,
                $idPersona,
                (string) $option['eleccion']
            );
        } else {
            $result = TurnoResolucionService::reubicarComoPaciente(
                (int) $turno->id_turnos,
                $idPersona,
                [
                    'fecha' => (string) ($option['fecha'] ?? ''),
                    'hora' => (string) ($option['hora'] ?? ''),
                    'id_profesional_efector_servicio' => (int) ($option['id_profesional_efector_servicio'] ?? 0),
                    'id_efector' => (int) ($option['id_efector'] ?? 0),
                ]
            );
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'shortlist_accepted',
            (int) $res->id,
            null,
            $idPersona,
            $optionId,
            ['id_turno' => (int) $turno->id_turnos],
            $option
        );

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $options
     */
    public function formatPushBodySuffix(array $options, ?array $config = null): string
    {
        if ($options === []) {
            return '';
        }

        $config = $config ?? AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $pushCfg = is_array($config['push'] ?? null) ? $config['push'] : [];
        $template = (string) ($pushCfg['body_suffix_template'] ?? ' Opciones: {{options_summary}}');

        $labels = [];
        foreach ($options as $opt) {
            $labels[] = (string) ($opt['label'] ?? '');
        }
        $summary = implode('; ', array_filter($labels));

        return str_replace('{{options_summary}}', $summary, $template);
    }

    /**
     * @param list<array<string, mixed>> $options
     */
    private function persistShortlist(TurnoResolucion $res, array $options): void
    {
        $meta = [];
        if ($res->meta_json !== null && $res->meta_json !== '') {
            $decoded = json_decode((string) $res->meta_json, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $meta['shortlist'] = [
            'generated_at' => date('Y-m-d H:i:s'),
            'options' => $options,
        ];
        $res->meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $res->save(false);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStoredOption(TurnoResolucion $res, string $optionId): ?array
    {
        $optionId = trim($optionId);
        if ($optionId === '' || $res->meta_json === null || $res->meta_json === '') {
            return null;
        }

        $meta = json_decode((string) $res->meta_json, true);
        if (!is_array($meta) || !is_array($meta['shortlist']['options'] ?? null)) {
            return null;
        }

        foreach ($meta['shortlist']['options'] as $opt) {
            if (is_array($opt) && (string) ($opt['option_id'] ?? '') === $optionId) {
                return $opt;
            }
        }

        return null;
    }
}
