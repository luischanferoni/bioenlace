<?php

namespace common\components\Person\Representation\Service;

use common\models\Person\PersonRepresentationPref;

/**
 * Preferencias de notificación (N9) y extensión futura de configuración.
 */
final class PersonRepresentationPreferenceService
{
    /**
     * @return array{notify_on_representative_action: bool, updated_at: string|null}
     */
    public function getForPersona(int $idPersona): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('id_persona inválido.');
        }

        $row = PersonRepresentationPref::findOne($idPersona);

        return [
            'notify_on_representative_action' => $row !== null && (bool) $row->notify_on_representative_action,
            'updated_at' => $row !== null ? (string) $row->updated_at : null,
        ];
    }

    public function shouldNotifyOnRepresentativeAction(int $idPersona): bool
    {
        $prefs = $this->getForPersona($idPersona);

        return $prefs['notify_on_representative_action'];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{notify_on_representative_action: bool, updated_at: string}
     */
    public function saveForPersona(int $idPersona, array $input): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('id_persona inválido.');
        }

        if (!array_key_exists('notify_on_representative_action', $input)) {
            throw new \InvalidArgumentException('notify_on_representative_action es obligatorio.');
        }

        $notify = filter_var($input['notify_on_representative_action'], FILTER_VALIDATE_BOOLEAN);
        $now = gmdate('Y-m-d H:i:s');

        $row = PersonRepresentationPref::findOne($idPersona);
        if ($row === null) {
            $row = new PersonRepresentationPref();
            $row->id_persona = $idPersona;
        }
        $row->notify_on_representative_action = $notify ? 1 : 0;
        $row->updated_at = $now;
        $row->save(false);

        return [
            'notify_on_representative_action' => $notify,
            'updated_at' => $now,
        ];
    }
}
