<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\models\Emergency\EfectorEmergencyConfig;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;

/**
 * Evalúa incumplimiento SLA por fila de tablero.
 */
final class GuardiaSlaService
{
    /**
     * @return array{sla_violado: bool, sla_tipo: string|null, sla_umbral_minutos: int|null}
     */
    public function evaluate(Guardia $guardia, int $minutosEspera, ?string $circuitoEstado, ?int $prioridadTriage): array
    {
        $config = EfectorEmergencyConfig::forEfector((int) $guardia->id_efector);
        $sinTriage = $circuitoEstado === CircuitoEstado::ESPERA_TRIAGE
            || $prioridadTriage === null;

        if ($sinTriage) {
            $umbral = (int) $config->minutos_espera_triage;
            if ($minutosEspera > $umbral) {
                return [
                    'sla_violado' => true,
                    'sla_tipo' => 'triage',
                    'sla_umbral_minutos' => $umbral,
                ];
            }

            return ['sla_violado' => false, 'sla_tipo' => null, 'sla_umbral_minutos' => null];
        }

        if (in_array($circuitoEstado, [CircuitoEstado::EN_ATENCION, CircuitoEstado::FINALIZADO, CircuitoEstado::DERIVADO], true)) {
            return ['sla_violado' => false, 'sla_tipo' => null, 'sla_umbral_minutos' => null];
        }

        $nivel = $prioridadTriage ?? 3;
        $umbral = $config->minutosEsperaMedicoPorNivel((int) $nivel);
        if ($minutosEspera > $umbral) {
            return [
                'sla_violado' => true,
                'sla_tipo' => 'medico',
                'sla_umbral_minutos' => $umbral,
            ];
        }

        return ['sla_violado' => false, 'sla_tipo' => null, 'sla_umbral_minutos' => null];
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
