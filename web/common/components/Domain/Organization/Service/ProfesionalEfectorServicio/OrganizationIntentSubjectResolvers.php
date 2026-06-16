<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\models\ProfesionalEfectorServicio as ProfesionalEfectorServicioRecord;
use Yii;

/**
 * Resolución de sujeto PES para intents condición laboral / organización.
 */
final class OrganizationIntentSubjectResolvers
{
    /**
     * @param array<string, mixed> $body
     */
    public static function hydratePesOwnInEfector(string $intentId, array &$body): void
    {
        unset($intentId);

        if (!isset($body['draft']) || !is_array($body['draft'])) {
            $body['draft'] = [];
        }
        $draft = &$body['draft'];

        if ((int) ($draft['id_profesional_efector_servicio'] ?? 0) > 0) {
            return;
        }

        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idEfector <= 0 || $idPersona <= 0) {
            return;
        }

        $idServicio = (int) ($draft['id_servicio'] ?? 0);
        $pes = null;
        if ($idServicio > 0) {
            $pes = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
                $idPersona,
                $idEfector,
                $idServicio
            );
        } else {
            $pes = ProfesionalEfectorServicioRecord::find()
                ->where([
                    'id_persona' => $idPersona,
                    'id_efector' => $idEfector,
                    'deleted_at' => null,
                ])
                ->orderBy(['id' => SORT_ASC])
                ->one();
        }

        if ($pes === null) {
            return;
        }

        $draft['id_profesional_efector_servicio'] = (int) $pes->id;
        $draft['id_efector'] = $idEfector;
        if ($idServicio <= 0) {
            $draft['id_servicio'] = (int) $pes->id_servicio;
        }
    }

    /**
     * Staff: el sujeto se elige vía listado (open_ui en YAML); solo normaliza efector en draft.
     *
     * @param array<string, mixed> $body
     */
    public static function hydratePesStaffInEfector(string $intentId, array &$body): void
    {
        unset($intentId);

        if (!isset($body['draft']) || !is_array($body['draft'])) {
            $body['draft'] = [];
        }
        $draft = &$body['draft'];

        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector > 0) {
            $draft['id_efector'] = $idEfector;
        }
    }
}
