<?php

namespace common\components\Domain\Organization\Efector\Application\Service;

use common\models\Organization\CommercialSignupDraft;
use Yii;
use yii\db\IntegrityException;

final class CommercialSignupDraftService
{
    public const RETENTION_DAYS = 30;
    private const SCHEMA_VERSION = 1;

    /**
     * Saves the mutable onboarding state without creating an efector or checkout.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(int $idPersona, array $input): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('La identidad verificada no es válida.');
        }
        $this->expireInactiveDrafts();

        $sector = strtoupper(trim((string) ($input['sector'] ?? '')));
        if ($sector !== '' && !in_array($sector, ['PUBLICO', 'PRIVADO'], true)) {
            throw new \InvalidArgumentException('El sector del borrador es inválido.');
        }
        foreach (['efector', 'plan'] as $field) {
            if (isset($input[$field]) && !is_array($input[$field])) {
                throw new \InvalidArgumentException('Los datos del borrador son inválidos.');
            }
        }
        $step = max(0, min(2, (int) ($input['current_step'] ?? 0)));
        $ministeryId = isset($input['id_billing_account_ministerio'])
            ? (int) $input['id_billing_account_ministerio']
            : null;
        if ($ministeryId !== null && $ministeryId <= 0) {
            $ministeryId = null;
        }
        $payerEmail = trim((string) ($input['payer_email'] ?? ''));
        $contactPhone = ContactPhoneValidator::normalizeMobile((string) ($input['contact_phone'] ?? '')) ?? '';
        ContactPhoneValidator::assertValidMobile($contactPhone, 'Teléfono de contacto');
        $checkoutRequestKey = strtolower(trim((string) ($input['checkout_request_key'] ?? '')));
        if ($checkoutRequestKey !== '' && !preg_match('/^[a-f0-9]{48}$/', $checkoutRequestKey)) {
            throw new \InvalidArgumentException('La clave de reanudación del checkout es inválida.');
        }

        $efectorJson = $this->encodeOptionalArray($input['efector'] ?? null);
        $planJson = $this->encodeOptionalArray($input['plan'] ?? null);
        $now = date('Y-m-d H:i:s');
        $draft = CommercialSignupDraft::findOne(['id_persona' => $idPersona]);
        if ($draft === null) {
            $draft = new CommercialSignupDraft();
            $draft->id_persona = $idPersona;
            $draft->status = CommercialSignupDraft::STATUS_DRAFT;
            $draft->current_step = 0;
            $draft->schema_version = self::SCHEMA_VERSION;
            $draft->created_at = $now;
        } elseif ($draft->status === CommercialSignupDraft::STATUS_ACTIVE) {
            throw new \InvalidArgumentException('El espacio ya fue activado y no tiene un borrador editable.');
        } elseif ($draft->status === CommercialSignupDraft::STATUS_EXPIRED) {
            $draft->status = CommercialSignupDraft::STATUS_DRAFT;
            $draft->checkout_key = null;
            $draft->checkout_request_key = null;
        }

        $draft->sector = $sector !== '' ? $sector : null;
        $draft->efector_json = $efectorJson;
        $draft->id_billing_account_ministerio = $ministeryId;
        $draft->plan_json = $planJson;
        $draft->payer_email = $payerEmail !== '' ? $payerEmail : null;
        $draft->contact_phone = $contactPhone !== '' ? $contactPhone : null;
        $draft->current_step = $step;
        $draft->checkout_request_key = $checkoutRequestKey !== ''
            ? $checkoutRequestKey
            : $draft->checkout_request_key;
        $draft->schema_version = self::SCHEMA_VERSION;
        $draft->last_activity_at = $now;
        if ($draft->status !== CommercialSignupDraft::STATUS_CHECKOUT_PENDING) {
            $draft->status = CommercialSignupDraft::STATUS_DRAFT;
            $draft->expires_at = date('Y-m-d H:i:s', strtotime('+' . self::RETENTION_DAYS . ' days'));
        }
        $draft->updated_at = $now;

        try {
            if (!$draft->save(false)) {
                throw new \RuntimeException('No se pudo guardar el borrador comercial.');
            }
        } catch (IntegrityException $exception) {
            $concurrentDraft = CommercialSignupDraft::findOne(['id_persona' => $idPersona]);
            if ($concurrentDraft === null) {
                throw $exception;
            }
            throw new \DomainException(
                'El borrador se está guardando desde otra solicitud. Reintentá en unos segundos.',
                0,
                $exception
            );
        }

        return $this->toArray($draft);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForPersona(int $idPersona): ?array
    {
        $this->expireInactiveDrafts();
        $draft = CommercialSignupDraft::findOne(['id_persona' => $idPersona]);
        if ($draft === null) {
            return null;
        }

        return $this->toArray($draft);
    }

    private function expireInactiveDrafts(): void
    {
        $now = date('Y-m-d H:i:s');
        Yii::$app->db->createCommand()->update(
            CommercialSignupDraft::tableName(),
            [
                'status' => CommercialSignupDraft::STATUS_EXPIRED,
                'sector' => null,
                'efector_json' => null,
                'id_billing_account_ministerio' => null,
                'plan_json' => null,
                'payer_email' => null,
                'contact_phone' => null,
                'current_step' => 0,
                'checkout_request_key' => null,
                'checkout_key' => null,
                'updated_at' => $now,
            ],
            [
                'and',
                ['status' => CommercialSignupDraft::STATUS_DRAFT],
                ['<=', 'expires_at', $now],
            ]
        )->execute();
    }

    public function markCheckoutPending(int $idPersona, string $checkoutKey): void
    {
        $draft = CommercialSignupDraft::findOne(['id_persona' => $idPersona]);
        if ($draft === null) {
            throw new \RuntimeException('No se encontró el borrador asociado al checkout.');
        }
        $draft->status = CommercialSignupDraft::STATUS_CHECKOUT_PENDING;
        $draft->checkout_key = $checkoutKey;
        $draft->updated_at = date('Y-m-d H:i:s');
        if (!$draft->save(false, ['status', 'checkout_key', 'updated_at'])) {
            throw new \RuntimeException('No se pudo vincular el checkout al borrador.');
        }
    }

    public function syncCheckoutStatus(int $idPersona, string $status): void
    {
        $draft = CommercialSignupDraft::findOne(['id_persona' => $idPersona]);
        if ($draft === null) {
            return;
        }
        if ($status === 'ACTIVE') {
            $draft->status = CommercialSignupDraft::STATUS_ACTIVE;
        } elseif (in_array($status, ['FAILED', 'CANCELLED', 'EXPIRED'], true)) {
            $draft->status = CommercialSignupDraft::STATUS_DRAFT;
            $draft->checkout_key = null;
            $draft->checkout_request_key = null;
        } else {
            return;
        }
        $draft->updated_at = date('Y-m-d H:i:s');
        if (!$draft->save(false, ['status', 'checkout_key', 'checkout_request_key', 'updated_at'])) {
            throw new \RuntimeException('No se pudo sincronizar el estado del borrador con el checkout.');
        }
    }

    /**
     * Deletes drafts that have been expired longer than the retention window.
     *
     * Only EXPIRED drafts are removed: a CHECKOUT_PENDING draft may still be paid,
     * and the commercial audit lives in the billing tables, not in this row.
     */
    public function purgeExpired(?\DateTimeImmutable $asOf = null): int
    {
        $this->expireInactiveDrafts();
        $cutoff = ($asOf ?? new \DateTimeImmutable())
            ->modify('-' . self::RETENTION_DAYS . ' days')
            ->format('Y-m-d H:i:s');

        return (int) Yii::$app->db->createCommand()->delete(
            CommercialSignupDraft::tableName(),
            [
                'and',
                ['status' => CommercialSignupDraft::STATUS_EXPIRED],
                ['<=', 'updated_at', $cutoff],
            ]
        )->execute();
    }

    /**
     * Marks the draft as activated once the first approved payment provisions the space.
     *
     * Idempotent: repeated webhooks or retries keep the same ACTIVE state and never
     * reopen the mutable draft.
     */
    public function markActivated(int $idPersona, ?string $checkoutKey = null): void
    {
        if ($idPersona <= 0) {
            return;
        }
        $draft = CommercialSignupDraft::findOne(['id_persona' => $idPersona]);
        if ($draft === null || $draft->status === CommercialSignupDraft::STATUS_ACTIVE) {
            return;
        }
        $draft->status = CommercialSignupDraft::STATUS_ACTIVE;
        if ($checkoutKey !== null && $checkoutKey !== '') {
            $draft->checkout_key = $checkoutKey;
        }
        $now = date('Y-m-d H:i:s');
        $draft->last_activity_at = $now;
        $draft->updated_at = $now;
        if (!$draft->save(false, ['status', 'checkout_key', 'last_activity_at', 'updated_at'])) {
            throw new \RuntimeException('No se pudo marcar el borrador comercial como activado.');
        }
    }

    /**
     * @param mixed $value
     */
    private function encodeOptionalArray($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Los datos del borrador son inválidos.');
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || strlen($encoded) > 20000) {
            throw new \InvalidArgumentException('Los datos del borrador superan el tamaño permitido.');
        }
        return $encoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(CommercialSignupDraft $draft): array
    {
        return [
            'status' => (string) $draft->status,
            'sector' => $draft->sector,
            'efector' => $this->decodeOptionalArray($draft->efector_json),
            'id_billing_account_ministerio' => $draft->id_billing_account_ministerio !== null
                ? (int) $draft->id_billing_account_ministerio
                : null,
            'plan' => $this->decodeOptionalArray($draft->plan_json),
            'payer_email' => $draft->payer_email,
            'contact_phone' => $draft->contact_phone,
            'current_step' => (int) $draft->current_step,
            'checkout_request_key' => $draft->checkout_request_key,
            'checkout_key' => $draft->checkout_key,
            'schema_version' => (int) $draft->schema_version,
            'expires_at' => (string) $draft->expires_at,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeOptionalArray(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('El borrador guardado tiene un formato inválido.');
        }
        return $decoded;
    }
}
