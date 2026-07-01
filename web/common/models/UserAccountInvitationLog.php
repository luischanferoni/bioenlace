<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Auditoría de invitaciones y activación de cuentas staff.
 *
 * @property int $id
 * @property int $id_user
 * @property string $action
 * @property int|null $id_actor_user
 * @property string|null $meta
 * @property int $created_at
 */
class UserAccountInvitationLog extends ActiveRecord
{
    public const ACTION_CREATED = 'created';

    public const ACTION_EMAIL_SENT = 'email_sent';

    public const ACTION_CODE_GENERATED = 'code_generated';

    public const ACTION_ACTIVATED = 'activated';

    public const ACTION_REVOKED = 'revoked';

    public const ACTION_EMAIL_RESENT = 'email_resent';

    public static function tableName(): string
    {
        return '{{%user_account_invitation_log}}';
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function record(int $idUser, string $action, ?int $idActorUser = null, array $meta = []): self
    {
        $row = new self();
        $row->id_user = $idUser;
        $row->action = $action;
        $row->id_actor_user = $idActorUser;
        $row->meta = $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE);
        $row->created_at = time();
        $row->save(false);

        return $row;
    }

    /**
     * @return list<self>
     */
    public static function listForUser(int $idUser, int $limit = 20): array
    {
        return self::find()
            ->where(['id_user' => $idUser])
            ->orderBy(['id' => SORT_DESC])
            ->limit($limit)
            ->all();
    }
}
