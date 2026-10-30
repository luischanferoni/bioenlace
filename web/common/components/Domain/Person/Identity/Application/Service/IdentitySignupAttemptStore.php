<?php

namespace common\components\Domain\Person\Identity\Application\Service;

use Yii;
use yii\db\Expression;

final class IdentitySignupAttemptStore
{
    public const STATUS_VERIFICATION_APPROVED = 'verification_approved';
    public const STATUS_VERIFICATION_REJECTED = 'verification_rejected';
    public const STATUS_VERIFICATION_PENDING = 'verification_pending';
    public const STATUS_VERIFICATION_UNKNOWN = 'verification_unknown';
    public const STATUS_ACCOUNT_CREATED = 'account_created';

    public function recordVerification(string $verificationId, string $status): void
    {
        $status = $this->normalizeStatus($status);
        $now = new Expression('CURRENT_TIMESTAMP');

        Yii::$app->db->createCommand()->upsert(
            '{{%identity_signup_attempt}}',
            [
                'verification_id_hash' => hash('sha256', $verificationId),
                'status' => $status,
                'attempt_count' => 1,
                'last_attempt_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'status' => $status,
                'attempt_count' => new Expression('attempt_count + 1'),
                'last_attempt_at' => $now,
                'updated_at' => $now,
            ]
        )->execute();
    }

    public function markAccountCreated(string $verificationId, int $idPersona, int $idUser): void
    {
        $hash = hash('sha256', $verificationId);
        Yii::$app->db->createCommand()->update(
            '{{%identity_signup_attempt}}',
            [
                'status' => self::STATUS_ACCOUNT_CREATED,
                'id_persona' => $idPersona,
                'id_user' => $idUser,
                'updated_at' => new Expression('CURRENT_TIMESTAMP'),
            ],
            ['verification_id_hash' => $hash]
        )->execute();

        $attempt = Yii::$app->db->createCommand(
            'SELECT status, id_persona, id_user FROM {{%identity_signup_attempt}} WHERE verification_id_hash = :hash',
            [':hash' => $hash]
        )->queryOne();
        if (!is_array($attempt)
            || $attempt['status'] !== self::STATUS_ACCOUNT_CREATED
            || (int) $attempt['id_persona'] !== $idPersona
            || (int) $attempt['id_user'] !== $idUser) {
            throw new \RuntimeException('No se pudo persistir el estado final del alta de identidad.');
        }
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'approved' => self::STATUS_VERIFICATION_APPROVED,
            'rejected' => self::STATUS_VERIFICATION_REJECTED,
            'pending' => self::STATUS_VERIFICATION_PENDING,
            default => self::STATUS_VERIFICATION_UNKNOWN,
        };
    }
}
