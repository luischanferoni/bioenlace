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
        if ($idEfector > 0) {
            $draft['id_efector'] = $idEfector;
        }
        if ($idEfector <= 0 || $idPersona <= 0) {
            return;
        }

        $idServicio = (int) ($draft['id_servicio'] ?? 0);
        if ($idServicio <= 0) {
            return;
        }

        $pes = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
            $idPersona,
            $idEfector,
            $idServicio
        );

        if ($pes === null) {
            return;
        }

        $draft['id_profesional_efector_servicio'] = (int) $pes->id;
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

        // Inicio de flow staff: no arrastrar PES de sesión ni borrador previo; el sujeto se elige en el listado.
        $subintentId = trim((string) ($body['subintent_id'] ?? ''));
        if ($subintentId === '') {
            unset($draft['id_profesional_efector_servicio']);
        }
    }
}
