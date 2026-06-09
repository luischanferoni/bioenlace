<?php

namespace common\models\Person;

use yii\db\ActiveRecord;

/**
 * Auditoría de acciones sobre vínculos de representación.
 *
 * @property int $id
 * @property int|null $person_related_id
 * @property int $actor_persona_id
 * @property int $subject_persona_id
 * @property string $action
 * @property int|null $id_user
 * @property string|null $payload_json
 * @property string $created_at
 */
class PersonRelatedAuditLog extends ActiveRecord
{
    public const ACTION_LINK_REQUESTED = 'link_requested';
    public const ACTION_DELEGATION_DESIGNATED = 'delegation_designated';
    public const ACTION_DELEGATION_REVOKED = 'delegation_revoked';
    public const ACTION_LINK_VERIFIED = 'link_verified';
    public const ACTION_LINK_BLOCKED = 'link_blocked';
    public const ACTION_LINK_REVOKED = 'link_revoked';
    public const ACTION_TURNO_CREATED = 'turno_created';
    public const ACTION_TURNO_CANCELLED = 'turno_cancelled';
    public const ACTION_MOTIVOS_SENT = 'motivos_sent';
    public const ACTION_CARE_PACK_ASSISTANCE = 'care_pack_assistance';
    public const ACTION_HISTORIA_ACCESSED = 'historia_accesed';
    public const ACTION_CARE_PLAN_ACCESSED = 'care_plan_accessed';

    public static function tableName(): string
    {
        return '{{%person_related_audit_log}}';
    }

    public function rules(): array
    {
        return [
            [['actor_persona_id', 'subject_persona_id', 'action', 'created_at'], 'required'],
            [['person_related_id', 'actor_persona_id', 'subject_persona_id', 'id_user'], 'integer'],
            [['payload_json', 'created_at'], 'safe'],
            [['action'], 'string', 'max' => 64],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function record(
        string $action,
        int $actorPersonaId,
        int $subjectPersonaId,
        ?int $personRelatedId = null,
        ?int $idUser = null,
        array $payload = []
    ): self {
        $row = new static();
        $row->action = $action;
        $row->actor_persona_id = $actorPersonaId;
        $row->subject_persona_id = $subjectPersonaId;
        $row->person_related_id = $personRelatedId;
        $row->id_user = $idUser;
        $row->payload_json = $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $row->created_at = gmdate('Y-m-d H:i:s');
        $row->save(false);

        return $row;
    }
}
