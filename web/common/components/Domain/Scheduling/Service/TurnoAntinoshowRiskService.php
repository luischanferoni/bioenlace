<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use yii\db\Query;

/**
 * Score de riesgo de no-show (v1: reglas sobre historial en BD).
 */
final class TurnoAntinoshowRiskService
{
    public const AGENT_ID = 'turno-antinoshow';

    /**
     * @return array<string, mixed>
     */
    public function assess(Turno $turno): array
    {
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $riskCfg = is_array($config['risk'] ?? null) ? $config['risk'] : [];

        $lookbackMonths = max(1, (int) ($riskCfg['lookback_months'] ?? 6));
        $since = date('Y-m-d', strtotime('-' . $lookbackMonths . ' months'));

        $idPersona = (int) $turno->id_persona;
        $idEfector = (int) $turno->id_efector;
        $idTurno = (int) $turno->id_turnos;

        $noShowCount = (int) (new Query())
            ->from(['t' => Turno::tableName()])
            ->where([
                't.id_persona' => $idPersona,
                't.estado' => Turno::ESTADO_SIN_ATENDER,
                't.estado_motivo' => Turno::ESTADO_MOTIVO_SIN_ATENDER_PACIENTE,
            ])
            ->andWhere(['>=', 't.fecha', $since])
            ->andWhere(['<>', 't.id_turnos', $idTurno])
            ->count('*', Turno::getDb());

        $attendedCount = (int) (new Query())
            ->from(['t' => Turno::tableName()])
            ->where([
                't.id_persona' => $idPersona,
                't.id_efector' => $idEfector,
                't.estado' => Turno::ESTADO_ATENDIDO,
            ])
            ->andWhere(['<>', 't.id_turnos', $idTurno])
            ->count('*', Turno::getDb());

        $leadDays = $this->leadDaysUntilAppointment($turno);
        $firstVisit = $attendedCount === 0;

        $riskLevel = self::computeRiskLevel($noShowCount, $leadDays, $firstVisit, $riskCfg);

        return [
            'risk_level' => $riskLevel,
            'no_show_count' => $noShowCount,
            'lead_days' => $leadDays,
            'is_first_visit' => $firstVisit,
            'confirmed' => $turno->confirmado_en !== null && $turno->confirmado_en !== '',
        ];
    }

    /**
     * @param array<string, mixed> $riskCfg
     */
    public static function computeRiskLevel(int $noShowCount, int $leadDays, bool $firstVisit, array $riskCfg): string
    {
        $highMin = (int) ($riskCfg['high_min_no_shows'] ?? 2);
        $mediumMin = (int) ($riskCfg['medium_min_no_shows'] ?? 1);
        $longLead = (int) ($riskCfg['long_lead_days'] ?? 21);

        if ($noShowCount >= $highMin) {
            return 'high';
        }
        if ($noShowCount >= $mediumMin || $leadDays >= $longLead) {
            return 'medium';
        }
        if ($firstVisit) {
            $level = (string) ($riskCfg['first_visit_level'] ?? 'medium');

            return in_array($level, ['high', 'medium', 'low'], true) ? $level : 'medium';
        }

        return 'low';
    }

    private function leadDaysUntilAppointment(Turno $turno): int
    {
        $fechaAlta = trim((string) ($turno->fecha_alta ?? ''));
        if ($fechaAlta === '') {
            return 0;
        }

        try {
            $cita = new \DateTimeImmutable((string) $turno->fecha . ' ' . substr((string) $turno->hora, 0, 5));
            $alta = new \DateTimeImmutable($fechaAlta);

            return max(0, (int) floor(($cita->getTimestamp() - $alta->getTimestamp()) / 86400));
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
