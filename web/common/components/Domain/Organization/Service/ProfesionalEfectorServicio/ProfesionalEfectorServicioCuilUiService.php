<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\components\Domain\Person\Service\PersonCuilService;
use common\models\Person\Persona;

/**
 * UI y submit de CUIL al alta PES (flujo asistente).
 */
final class ProfesionalEfectorServicioCuilUiService
{
    /**
     * @param array<string, mixed> $fromClient
     * @return array<string, mixed>
     */
    public static function buildValuesForGet(int $idEfector, array $fromClient): array
    {
        $idPersona = (int) ($fromClient['id_persona'] ?? 0);
        $label = '';
        if ($idPersona > 0) {
            $persona = Persona::findOne(['id_persona' => $idPersona]);
            if ($persona !== null) {
                $label = trim((string) $persona->apellido . ', ' . (string) $persona->nombre);
                if (PersonCuilService::personaTieneCuil($persona)) {
                    return [
                        'id_persona' => (string) $idPersona,
                        'cuil' => (string) $persona->cuil,
                        'persona_label' => $label,
                        'ya_tiene_cuil' => '1',
                    ];
                }
            }
        }

        return [
            'id_persona' => $idPersona > 0 ? (string) $idPersona : '',
            'cuil' => '',
            'persona_label' => $label,
            'ya_tiene_cuil' => '0',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{data: array<string, mixed>}
     */
    public static function submit(int $idEfector, array $post): array
    {
        unset($idEfector);

        $idPersona = (int) ($post['id_persona'] ?? 0);
        $cuil = (string) ($post['cuil'] ?? '');
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('id_persona es requerido.');
        }

        $normalized = PersonCuilService::ensureOnPersona($idPersona, $cuil);

        return [
            'data' => [
                'id_persona' => $idPersona,
                'cuil' => $normalized,
            ],
        ];
    }
}
