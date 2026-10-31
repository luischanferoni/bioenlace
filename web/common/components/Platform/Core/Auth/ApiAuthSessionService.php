<?php

namespace common\components\Platform\Core\Auth;

use Yii;
use yii\db\Query;

/**
 * Registro revocable para JWT emitidos por Bioenlace.
 */
final class ApiAuthSessionService
{
    public static function issue(int $userId, int $expiresAt, string $client = 'unknown'): string
    {
        if ($userId <= 0 || $expiresAt <= time()) {
            throw new \InvalidArgumentException('La sesión JWT requiere usuario y vencimiento válidos.');
        }

        $jti = Yii::$app->security->generateRandomString(64);
        $table = Yii::$app->db->schema->getRawTableName('{{%auth_session}}');
        Yii::$app->db->createCommand()->insert($table, [
            'jti' => $jti,
            'id_user' => $userId,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'client' => mb_substr($client, 0, 64),
            'created_at' => time(),
        ])->execute();

        return $jti;
    }

    public static function isActive(string $jti, int $userId): bool
    {
        if ($jti === '' || $userId <= 0) {
            return false;
        }

        $table = Yii::$app->db->schema->getRawTableName('{{%auth_session}}');

        return (new Query())
            ->from($table)
            ->where([
                'jti' => $jti,
                'id_user' => $userId,
                'revoked_at' => null,
            ])
            ->andWhere(['>', 'expires_at', time()])
            ->exists(Yii::$app->db);
    }

    public static function revoke(string $jti, ?int $userId = null): void
    {
        if ($jti === '') {
            return;
        }

        $table = Yii::$app->db->schema->getRawTableName('{{%auth_session}}');
        $condition = ['jti' => $jti, 'revoked_at' => null];
        if ($userId !== null) {
            $condition['id_user'] = $userId;
        }

        Yii::$app->db->createCommand()->update($table, [
            'revoked_at' => time(),
        ], $condition)->execute();
    }

    public static function revokeAllForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $table = Yii::$app->db->schema->getRawTableName('{{%auth_session}}');
        Yii::$app->db->createCommand()->update($table, [
            'revoked_at' => time(),
        ], [
            'id_user' => $userId,
            'revoked_at' => null,
        ])->execute();
    }
}
