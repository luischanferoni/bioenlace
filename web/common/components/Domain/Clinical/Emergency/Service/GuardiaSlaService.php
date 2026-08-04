<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\models\Emergency\EfectorEmergencyConfig;
use common\models\Guardia;

/**
 * Evalúa incumplimiento de plazos por fila de tablero.
 */
final class GuardiaSlaService
{
    public const TRIAGE_ESPERA_GRIS = 'gris';
    public const TRIAGE_ESPERA_NARANJA = 'naranja';
    public const TRIAGE_ESPERA_ROJO = 'rojo';

    /**
     * @return array{
     *   sla_violado: bool,
     *   sla_tipo: string|null,
     *   sla_umbral_minutos: int|null,
     *   triage_espera_nivel: string|null
     * }
     */
    public function evaluate(Guardia $guardia, int $minutosEspera, ?string $circuitoEstado, ?int $prioridadTriage): array
    {
        $config = EfectorEmergencyConfig::forEfector((int) $guardia->id_efector);
        $sinTriage = $circuitoEstado === CircuitoEstado::ESPERA_TRIAGE
            || $prioridadTriage === null;

        if ($sinTriage) {
            $umbral = (int) $config->minutos_espera_triage;
            $nivel = $this->nivelEsperaTriage($minutosEspera, $umbral);
            $violado = $nivel === self::TRIAGE_ESPERA_ROJO;

            return [
                'sla_violado' => $violado,
                'sla_tipo' => $violado ? 'triage' : null,
                'sla_umbral_minutos' => $umbral,
                'triage_espera_nivel' => $nivel,
            ];
        }

        if (in_array($circuitoEstado, [CircuitoEstado::EN_ATENCION, CircuitoEstado::FINALIZADO, CircuitoEstado::DERIVADO], true)) {
            return [
                'sla_violado' => false,
                'sla_tipo' => null,
                'sla_umbral_minutos' => null,
                'triage_espera_nivel' => null,
            ];
        }

        $nivel = $prioridadTriage ?? 3;
        $umbral = $config->minutosEsperaMedicoPorNivel((int) $nivel);
        if ($minutosEspera > $umbral) {
            return [
                'sla_violado' => true,
                'sla_tipo' => 'medico',
                'sla_umbral_minutos' => $umbral,
                'triage_espera_nivel' => null,
            ];
        }

        return [
            'sla_violado' => false,
            'sla_tipo' => null,
            'sla_umbral_minutos' => null,
            'triage_espera_nivel' => null,
        ];
    }

    /**
     * Semáforo de espera a triage: gris (&lt;50% del plazo), naranja (≥50%), rojo (vencido).
     */
    public function nivelEsperaTriage(int $minutosEspera, int $umbralMinutos): string
    {
        $minutos = max(0, $minutosEspera);
        $umbral = max(0, $umbralMinutos);
        if ($umbral <= 0) {
            return $minutos > 0 ? self::TRIAGE_ESPERA_ROJO : self::TRIAGE_ESPERA_GRIS;
        }
        if ($minutos > $umbral) {
            return self::TRIAGE_ESPERA_ROJO;
        }
        if ($minutos * 2 >= $umbral) {
            return self::TRIAGE_ESPERA_NARANJA;
        }

        return self::TRIAGE_ESPERA_GRIS;
    }

    /**
     * @return array<string, mixed>
     */
    public function configForEfector(int $idEfector): array
    {
        $c = EfectorEmergencyConfig::forEfector($idEfector);

        return [
            'id_efector' => (int) $c->id_efector,
            'minutos_espera_triage' => (int) $c->minutos_espera_triage,
            'minutos_espera_medico' => [
                1 => (int) $c->minutos_espera_medico_1,
                2 => (int) $c->minutos_espera_medico_2,
                3 => (int) $c->minutos_espera_medico_3,
                4 => (int) $c->minutos_espera_medico_4,
                5 => (int) $c->minutos_espera_medico_5,
            ],
        ];
    }
}
