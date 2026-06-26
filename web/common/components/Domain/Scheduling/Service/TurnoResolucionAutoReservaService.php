<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\EfectorTurnosConfig;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;
use Yii;

/**
 * Selección de candidato unívoco para auto-reserva en resolución (agente A01 D2).
 */
final class TurnoResolucionAutoReservaService
{
    public const AGENT_ID = 'turno-resolucion-auto-reserva';

    private TurnoResolucionShortlistService $shortlist;

    public function __construct(?TurnoResolucionShortlistService $shortlist = null)
    {
        $this->shortlist = $shortlist ?? new TurnoResolucionShortlistService();
    }

    public function isEnabledForEfector(int $idEfector): bool
    {
        if (!(Yii::$app->params['autonomous_agent_resolucion_auto_reserva_enabled'] ?? false)) {
            return false;
        }
        if ($idEfector <= 0) {
            return false;
        }

        $cfg = EfectorTurnosConfig::findOne(['id_efector' => $idEfector]);

        return $cfg !== null && (bool) ($cfg->auto_reserva_resolucion_habilitada ?? false);
    }

    /**
     * @param array<string, mixed> $prefs
     * @param array<string, mixed>|null $config
     * @return array<string, mixed>|null
     */
    public function pickCandidate(Turno $turno, TurnoResolucion $res, array $prefs, ?array $config = null): ?array
    {
        $config = $config ?? AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $shortlistConfig = AutonomousAgentMetadata::loadAgent(TurnoResolucionShortlistService::AGENT_ID) ?? [];
        $mergedConfig = array_replace_recursive($shortlistConfig, $config);

        $scored = $this->shortlist->buildScoredCandidates($turno, $res, $mergedConfig);
        if ($scored === []) {
            return null;
        }

        $filtered = self::applyHardPreferenceFilters($scored, $turno, $prefs);
        if ($filtered === []) {
            return null;
        }

        foreach ($filtered as $i => $row) {
            $filtered[$i]['score'] = (int) ($row['score'] ?? 0)
                + self::preferenceScoreBonus($turno, $row, $prefs, $mergedConfig);
        }

        usort($filtered, static function (array $a, array $b): int {
            return ($b['score'] <=> $a['score'])
                ?: strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''))
                ?: strcmp((string) ($a['hora'] ?? ''), (string) ($b['hora'] ?? ''));
        });

        return self::pickUnambiguousWinner($filtered, $config);
    }

    /**
     * @param list<array<string, mixed>> $scored
     * @param array<string, mixed> $config
     * @return array<string, mixed>|null
     */
    public static function pickUnambiguousWinner(array $scored, array $config): ?array
    {
        if ($scored === []) {
            return null;
        }

        $top = $scored[0];
        $second = $scored[1] ?? null;
        $minScore = (int) ($config['min_winner_score'] ?? 40);
        $minGap = (int) ($config['min_score_gap'] ?? 8);

        if ((int) ($top['score'] ?? 0) < $minScore) {
            return null;
        }
        if ($second !== null && ((int) ($top['score'] ?? 0) - (int) ($second['score'] ?? 0)) < $minGap) {
            return null;
        }

        return $top;
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @param array<string, mixed> $prefs
     * @return list<array<string, mixed>>
     */
    public static function applyHardPreferenceFilters(array $candidates, Turno $turno, array $prefs): array
    {
        $dias = is_array($prefs['dias_semana'] ?? null) ? $prefs['dias_semana'] : [];
        $franjas = is_array($prefs['franjas'] ?? null) ? $prefs['franjas'] : [];
        $tipoPref = isset($prefs['tipo_atencion_preferido']) && $prefs['tipo_atencion_preferido'] !== ''
            ? (string) $prefs['tipo_atencion_preferido']
            : null;

        $out = [];
        foreach ($candidates as $c) {
            $fecha = (string) ($c['fecha'] ?? '');
            $hora = substr((string) ($c['hora'] ?? ''), 0, 5);
            if ($dias !== [] && !self::fechaEnDiasPermitidos($fecha, $dias)) {
                continue;
            }
            if ($franjas !== [] && !self::horaEnFranjas($hora, $franjas)) {
                continue;
            }
            if ($tipoPref !== null) {
                $tipoCand = (string) ($c['tipo_atencion'] ?? $turno->tipo_atencion ?? Turno::TIPO_ATENCION_PRESENCIAL);
                if ($tipoCand !== $tipoPref) {
                    continue;
                }
            }
            $out[] = $c;
        }

        if (($prefs['mismo_pes_prioritario'] ?? true) && $out !== []) {
            $turnoPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
            if ($turnoPes > 0) {
                $samePes = array_values(array_filter(
                    $out,
                    static fn (array $c): bool => (int) ($c['id_profesional_efector_servicio'] ?? 0) === $turnoPes
                ));
                if ($samePes !== []) {
                    return $samePes;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $prefs
     * @param array<string, mixed> $config
     */
    public static function preferenceScoreBonus(Turno $turno, array $candidate, array $prefs, array $config): int
    {
        $weights = is_array($config['scoring'] ?? null) ? $config['scoring'] : [];
        $bonus = 0;

        $franjas = is_array($prefs['franjas'] ?? null) ? $prefs['franjas'] : [];
        $hora = substr((string) ($candidate['hora'] ?? ''), 0, 5);
        if ($franjas !== [] && self::horaEnFranjas($hora, $franjas)) {
            $bonus += (int) ($weights['preference_franja_match'] ?? 15);
        }

        $dias = is_array($prefs['dias_semana'] ?? null) ? $prefs['dias_semana'] : [];
        $fecha = (string) ($candidate['fecha'] ?? '');
        if ($dias !== [] && self::fechaEnDiasPermitidos($fecha, $dias)) {
            $bonus += (int) ($weights['preference_dia_match'] ?? 10);
        }

        $tipoPref = isset($prefs['tipo_atencion_preferido']) && $prefs['tipo_atencion_preferido'] !== ''
            ? (string) $prefs['tipo_atencion_preferido']
            : null;
        if ($tipoPref !== null) {
            $tipoCand = (string) ($candidate['tipo_atencion'] ?? $turno->tipo_atencion ?? Turno::TIPO_ATENCION_PRESENCIAL);
            if ($tipoCand === $tipoPref) {
                $bonus += (int) ($weights['preference_tipo_atencion_match'] ?? 15);
            }
        }

        return $bonus;
    }

    /**
     * @param list<int> $dias ISO-8601: 1=lunes … 7=domingo
     */
    public static function fechaEnDiasPermitidos(string $fecha, array $dias): bool
    {
        if ($fecha === '' || $dias === []) {
            return true;
        }
        try {
            $n = (int) (new \DateTimeImmutable($fecha))->format('N');
        } catch (\Throwable $e) {
            return false;
        }

        return in_array($n, array_map('intval', $dias), true);
    }

    /**
     * @param list<string> $franjas MANANA|TARDE|NOCHE
     */
    public static function horaEnFranjas(string $hora, array $franjas): bool
    {
        if ($hora === '' || $franjas === []) {
            return true;
        }

        $parts = explode(':', $hora);
        $h = (int) ($parts[0] ?? 0);
        $franja = self::franjaFromHour($h);
        $normalized = array_map(static fn ($f) => strtoupper((string) $f), $franjas);

        return in_array($franja, $normalized, true);
    }

    public static function franjaFromHour(int $hour): string
    {
        if ($hour >= 6 && $hour < 12) {
            return 'MANANA';
        }
        if ($hour >= 12 && $hour < 18) {
            return 'TARDE';
        }

        return 'NOCHE';
    }
}
