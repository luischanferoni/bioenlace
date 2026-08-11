<?php

namespace common\components\Domain\Clinical\Workflow;

use common\models\Clinical\Encounter;

/**
 * Composición de categorías de captura según actor (PES `servicios.item_name`).
 * No decide integridad: solo qué secciones entran al prompt y flags requerido/sugerido.
 */
final class EncounterCaptureActorCatalog
{
    public const ACTOR_ENFERMERIA = 'enfermeria';
    public const ACTOR_MEDICO = 'medico';

    public static function normalize(string $itemName): string
    {
        $n = mb_strtolower(trim($itemName));
        if ($n === 'enfermeria') {
            return self::ACTOR_ENFERMERIA;
        }
        if ($n === 'medico') {
            return self::ACTOR_MEDICO;
        }

        return $n;
    }

    /**
     * Pasos a insertar si faltan en la definición de la oferta clínica.
     *
     * @return list<array{titulo: string, modelo: string, requerido: bool, sugerido: bool}>
     */
    public static function extraSteps(string $actor, string $encounterClass): array
    {
        if ($actor !== self::ACTOR_ENFERMERIA) {
            return [];
        }
        if ($encounterClass === Encounter::ENCOUNTER_CLASS_IMP) {
            return [[
                'titulo' => 'Signos vitales',
                'modelo' => 'ConsultaAtencionesEnfermeria',
                'requerido' => false,
                'sugerido' => true,
            ]];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public static function suggestedModelos(string $actor, string $encounterClass): array
    {
        if ($actor !== self::ACTOR_ENFERMERIA) {
            return [];
        }
        if ($encounterClass === Encounter::ENCOUNTER_CLASS_IMP) {
            return ['ConsultaAtencionesEnfermeria', 'ConsultaBalanceHidrico'];
        }
        if ($encounterClass === Encounter::ENCOUNTER_CLASS_EMER) {
            return ['ConsultaAtencionesEnfermeria'];
        }

        return ['ConsultaAtencionesEnfermeria'];
    }
}
