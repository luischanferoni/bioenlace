<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\models\Scheduling\PersonaTurnosPerfil;
use common\models\Scheduling\PersonaTurnosPerfilMetrica;

/**
 * Lectura estricta del snapshot factual vigente.
 *
 * No aproxima ventanas ni scopes y nunca deriva etiquetas de riesgo.
 */
final class TurnoBehaviorProfileReader
{
    private TurnoBehaviorProfileContract $contract;

    public function __construct(?TurnoBehaviorProfileContract $contract = null)
    {
        $this->contract = $contract ?? new TurnoBehaviorProfileContract();
    }

    public function currentProfile(int $idPersona): ?PersonaTurnosPerfil
    {
        return PersonaTurnosPerfil::find()
            ->where([
                'id_persona' => $idPersona,
                'profile_contract_version' => $this->contract->version(),
                'is_current' => 1,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public function metric(
        int $idPersona,
        string $metricCode,
        string $scopeType,
        string $scopeId,
        int $windowDays
    ): ?PersonaTurnosPerfilMetrica {
        if (!in_array($windowDays, $this->contract->windowsDays(), true)) {
            return null;
        }
        $profile = $this->currentProfile($idPersona);
        if ($profile === null) {
            return null;
        }

        return PersonaTurnosPerfilMetrica::findOne([
            'id_perfil' => (int) $profile->id,
            'metric_code' => $metricCode,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'window_days' => $windowDays,
        ]);
    }
}
