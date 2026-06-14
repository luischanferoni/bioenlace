<?php

namespace common\components\Domain\Person\Service;

use common\models\Person\Persona;

/**
 * Actualiza el grupo Persona.identidad_basica (sin HTTP).
 */
final class PersonaIdentidadBasicaUpdateService
{
    private const ALLOWED = ['nombre', 'apellido', 'otro_nombre', 'otro_apellido'];

    /**
     * @param array<string, string> $fields solo claves a modificar
     * @return array{
     *   persona: Persona,
     *   before: array<string, string>,
     *   after: array<string, string>
     * }
     */
    public function update(int $idPersona, array $fields): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('id_persona inválido.');
        }

        $persona = Persona::findOne($idPersona);
        if ($persona === null) {
            throw new \InvalidArgumentException('Persona no encontrada.');
        }

        $before = [];
        $after = [];
        $persona->scenario = Persona::SCENARIOCREATEUPDATE;

        foreach ($fields as $key => $value) {
            if (!is_string($key) || !in_array($key, self::ALLOWED, true)) {
                continue;
            }
            if (!$persona->hasAttribute($key)) {
                continue;
            }
            $before[$key] = trim((string) $persona->getAttribute($key));
            $normalized = trim((string) $value);
            $after[$key] = $normalized;
            $persona->setAttribute($key, $normalized);
        }

        if ($after === []) {
            throw new \InvalidArgumentException('No hay campos válidos para actualizar.');
        }

        if (!$persona->save()) {
            $msg = json_encode($persona->getErrors(), JSON_UNESCAPED_UNICODE);
            throw new \InvalidArgumentException('No se pudo guardar la identidad: ' . ($msg !== false ? $msg : 'error de validación'));
        }

        return [
            'persona' => $persona,
            'before' => $before,
            'after' => $after,
        ];
    }
}
