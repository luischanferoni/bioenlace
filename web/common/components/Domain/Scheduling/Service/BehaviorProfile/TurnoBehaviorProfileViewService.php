<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\models\Scheduling\PersonaTurnosPerfilMetrica;

/**
 * DTO neutral y explicable del perfil factual para su titular.
 */
final class TurnoBehaviorProfileViewService
{
    private TurnoBehaviorProfileReader $reader;

    public function __construct(?TurnoBehaviorProfileReader $reader = null)
    {
        $this->reader = $reader ?? new TurnoBehaviorProfileReader();
    }

    /**
     * @return array<string, mixed>
     */
    public function forPerson(int $idPersona): array
    {
        $profile = $this->reader->currentProfile($idPersona);
        if ($profile === null) {
            return [
                'status' => 'UNAVAILABLE',
                'message' => 'Todavía no hay información suficiente para mostrar tu historial de turnos.',
                'metrics' => [],
            ];
        }

        $metrics = PersonaTurnosPerfilMetrica::find()
            ->where(['id_perfil' => (int) $profile->id])
            ->orderBy(['window_days' => SORT_ASC, 'scope_type' => SORT_ASC, 'metric_code' => SORT_ASC])
            ->asArray()
            ->all();

        return [
            'status' => 'CURRENT',
            'snapshot_ref' => (int) $profile->id,
            'contract_version' => (int) $profile->profile_contract_version,
            'as_of' => (string) $profile->as_of,
            'generated_at' => (string) $profile->generated_at,
            'completeness_status' => (string) $profile->completeness_status,
            'metrics' => array_map(static function (array $metric): array {
                return [
                    'code' => (string) $metric['metric_code'],
                    'scope_type' => (string) $metric['scope_type'],
                    'scope_id' => (string) $metric['scope_id'],
                    'window_days' => (int) $metric['window_days'],
                    'numerator' => (int) $metric['numerator'],
                    'denominator' => $metric['denominator'] !== null ? (int) $metric['denominator'] : null,
                    'value' => $metric['value'] !== null ? (float) $metric['value'] : null,
                    'sample_size' => (int) $metric['sample_size'],
                    'confidence_status' => (string) $metric['confidence_status'],
                ];
            }, $metrics),
            'disclaimer' => 'Estos datos describen hechos registrados y no son una calificación personal.',
        ];
    }
}
