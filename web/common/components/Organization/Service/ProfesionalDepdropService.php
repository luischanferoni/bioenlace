<?php

namespace common\components\Organization\Service;

use common\models\Especialidades;

/**
 * DepDrop profesión → especialidad (wizard alta profesional de salud).
 */
final class ProfesionalDepdropService
{
    public const URL_ESPECIALIDADES = '/api/v1/catalogos/especialidades-depdrop';

    /**
     * @param array<string, mixed> $post
     * @return array{output: list<array{id: string, name: string}>, selected: list<string>|string}
     */
    public static function especialidadesResponse(array $post): array
    {
        $out = [];

        if (!isset($post['depdrop_parents']) || $post['depdrop_parents'] === null) {
            return ['output' => $out, 'selected' => ''];
        }

        $profesionesId = $post['depdrop_parents'][0];
        $out = Especialidades::find()
            ->select(['CONCAT(id_especialidad, "-", id_profesion) AS id', 'nombre AS name'])
            ->where(['in', 'id_profesion', $profesionesId])
            ->asArray()
            ->all();

        $selected = [];
        if (!empty($post['depdrop_all_params']['especialidades_seleccionadas'])) {
            $decoded = json_decode((string) $post['depdrop_all_params']['especialidades_seleccionadas'], true);
            if (is_array($decoded)) {
                $selected = $decoded;
            }
        }

        return ['output' => $out, 'selected' => $selected];
    }
}
