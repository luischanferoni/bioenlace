<?php

namespace common\components\Domain\Organization\Service\Billing;

use common\components\Domain\Organization\Service\Entitlement\EfectorEncounterEntitlementService;
use common\models\BillingAccount;
use common\models\BillingAccountEfector;
use common\models\BillingSignupRequest;
use common\models\User;
use Yii;

/**
 * Cambios de membresía de pago (POOL) vs afiliación (AFILIADO) para un efector.
 */
final class BillingMembershipSwitchService
{
    /**
     * @return array<string, mixed>
     */
    public static function summaryForEfector(int $idEfector): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Efector inválido.');
        }

        $pool = BillingAccountEfector::find()
            ->where([
                'id_efector' => $idEfector,
                'rol_membresia' => BillingAccountEfector::ROL_POOL,
                'deleted_at' => null,
            ])
            ->with('account')
            ->one();

        $affiliates = BillingAccountEfector::find()
            ->where([
                'id_efector' => $idEfector,
                'rol_membresia' => BillingAccountEfector::ROL_AFILIADO,
                'deleted_at' => null,
            ])
            ->with('account')
            ->all();

        $poolAccount = $pool !== null ? $pool->account : null;
        $affList = [];
        foreach ($affiliates as $m) {
            $acc = $m->account;
            if ($acc === null) {
                continue;
            }
            $affList[] = [
                'id_billing_account' => (int) $acc->id,
                'nombre' => (string) $acc->nombre,
                'tipo' => (string) $acc->tipo,
            ];
        }

        return [
            'id_efector' => $idEfector,
            'pool' => $poolAccount !== null ? [
                'id_billing_account' => (int) $poolAccount->id,
                'nombre' => (string) $poolAccount->nombre,
                'tipo' => (string) $poolAccount->tipo,
                'contract' => EfectorEncounterEntitlementService::contractSummaryForAccount((int) $poolAccount->id),
            ] : null,
            'afiliaciones' => $affList,
        ];
    }

    /**
     * @param array<string, mixed> $paymentIn
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public static function desvincularPagoMinisterio(
        int $idEfector,
        int $ownerUserId,
        array $plan,
        array $paymentIn
    ): array {
        $pool = BillingAccountEfector::find()
            ->where([
                'id_efector' => $idEfector,
                'rol_membresia' => BillingAccountEfector::ROL_POOL,
                'deleted_at' => null,
            ])
            ->with('account')
            ->one();

        if ($pool === null || $pool->account === null) {
            throw new \InvalidArgumentException('El efector no consume cupo de ninguna cuenta.');
        }
        if ($pool->account->tipo !== BillingAccount::TIPO_MINISTERIO
            && $pool->account->tipo !== BillingAccount::TIPO_RED) {
            throw new \InvalidArgumentException('El pago actual no es de un ministerio/red; ya es cuenta propia.');
        }

        $idOld = (int) $pool->id_billing_account;
        $normalizedPlan = self::normalizePlanOrCopyFromAccount($plan, $idOld);

        $tx = Yii::$app->db->beginTransaction();
        try {
            $account = BillingAccountService::createAccount([
                'nombre' => 'Licencia propia — efector #' . $idEfector,
                'tipo' => BillingAccount::TIPO_EFECTOR,
                'notas' => 'Desvinculación de pago ministerial',
                'activo' => 1,
            ]);
            $account->owner_user_id = $ownerUserId;
            $account->save(false, ['owner_user_id', 'updated_at']);

            $amountUsd = InstitutionalEfectorSignupService::estimateMonthlyUsd(['classes' => $normalizedPlan]);

            $payment = SimulatedPaymentGateway::charge([
                'id_billing_account' => (int) $account->id,
                'amount_usd' => $amountUsd,
                'card_number' => $paymentIn['card_number'] ?? null,
                'card_holder' => $paymentIn['card_holder'] ?? null,
            ]);

            foreach ($normalizedPlan as $class => $cfg) {
                BillingAccountService::upsertEntitlement((int) $account->id, $class, [
                    'max_pes' => (int) $cfg['max_pes'],
                    'dictado_incluido' => !empty($cfg['dictado_incluido']),
                    'videollamada_permitida' => !empty($cfg['videollamada_permitida']),
                    'activo' => 1,
                ]);
            }

            BillingAccountService::detachEfector($idOld, $idEfector);
            BillingAccountService::attachEfector((int) $account->id, $idEfector, BillingAccountEfector::ROL_POOL);

            $aff = BillingAccountEfector::find()
                ->where([
                    'id_billing_account' => $idOld,
                    'id_efector' => $idEfector,
                    'rol_membresia' => BillingAccountEfector::ROL_AFILIADO,
                    'deleted_at' => null,
                ])
                ->one();
            if ($aff === null) {
                BillingAccountService::attachEfector($idOld, $idEfector, BillingAccountEfector::ROL_AFILIADO);
            }

            $tx->commit();

            return [
                'id_billing_account' => (int) $account->id,
                'previous_pool_account_id' => $idOld,
                'payment_reference' => (string) $payment->external_reference,
                'amount_usd' => (float) $payment->amount_usd,
            ];
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    public static function solicitarAsociarPagoMinisterio(
        int $idEfector,
        int $ownerUserId,
        int $idMinisterioAccount
    ): BillingSignupRequest {
        $ministerio = BillingAccount::findOne([
            'id' => $idMinisterioAccount,
            'tipo' => BillingAccount::TIPO_MINISTERIO,
            'activo' => 1,
            'deleted_at' => null,
        ]);
        if ($ministerio === null) {
            throw new \InvalidArgumentException('Ministerio inválido.');
        }

        $user = User::findOne($ownerUserId);
        $email = $user !== null ? (string) $user->email : ('user' . $ownerUserId . '@local');

        BillingAccountService::attachEfector(
            (int) $ministerio->id,
            $idEfector,
            BillingAccountEfector::ROL_AFILIADO
        );

        $req = new BillingSignupRequest();
        $req->tipo = BillingSignupRequest::TIPO_EFECTOR;
        $req->status = BillingSignupRequest::STATUS_PENDING;
        $req->nombre_organizacion = 'Solicitud cobertura pool ministerio #' . $ministerio->id;
        $req->sector = BillingSignupRequest::SECTOR_PUBLICO;
        $req->id_billing_account_ministerio = (int) $ministerio->id;
        $req->contacto_nombre = 'Admin';
        $req->contacto_apellido = 'Efector';
        $req->contacto_email = $email !== '' ? $email : ('efector' . $idEfector . '@bioenlace.local');
        $req->notas = 'Solicita mover POOL del efector #' . $idEfector . ' a la cuenta ministerio #' . $ministerio->id;
        $req->id_user = $ownerUserId;
        $req->id_efector = $idEfector;
        if (!$req->save()) {
            throw new \InvalidArgumentException('No se pudo crear la solicitud: ' . json_encode($req->getErrors()));
        }

        return $req;
    }

    public static function approvePoolMoveToMinisterio(int $idEfector, int $idMinisterioAccount): void
    {
        $ministerio = BillingAccount::findOne([
            'id' => $idMinisterioAccount,
            'tipo' => BillingAccount::TIPO_MINISTERIO,
            'deleted_at' => null,
        ]);
        if ($ministerio === null) {
            throw new \InvalidArgumentException('Ministerio inválido.');
        }

        $pool = BillingAccountEfector::find()
            ->where([
                'id_efector' => $idEfector,
                'rol_membresia' => BillingAccountEfector::ROL_POOL,
                'deleted_at' => null,
            ])
            ->one();
        if ($pool !== null) {
            BillingAccountService::detachEfector((int) $pool->id_billing_account, $idEfector);
        }

        BillingAccountService::attachEfector(
            (int) $ministerio->id,
            $idEfector,
            BillingAccountEfector::ROL_POOL
        );
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, array{max_pes: int, dictado_incluido: bool, videollamada_permitida: bool}>
     */
    private static function normalizePlanOrCopyFromAccount(array $plan, int $idAccount): array
    {
        if (isset($plan['classes']) && is_array($plan['classes']) && $plan['classes'] !== []) {
            $out = [];
            foreach ($plan['classes'] as $class => $cfg) {
                $class = strtoupper((string) $class);
                if (!is_array($cfg)) {
                    continue;
                }
                $out[$class] = [
                    'max_pes' => max(1, (int) ($cfg['max_pes'] ?? 1)),
                    'dictado_incluido' => !empty($cfg['dictado_incluido']),
                    'videollamada_permitida' => !empty($cfg['videollamada_permitida']),
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }

        $summary = EfectorEncounterEntitlementService::contractSummaryForAccount($idAccount);
        $out = [];
        foreach ($summary as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $out[$code] = [
                'max_pes' => max(1, (int) ($row['max_pes'] ?? 1)),
                'dictado_incluido' => !empty($row['dictado_incluido']),
                'videollamada_permitida' => !empty($row['videollamada_permitida']),
            ];
        }
        if ($out === []) {
            $out['AMB'] = ['max_pes' => 5, 'dictado_incluido' => false, 'videollamada_permitida' => false];
        }

        return $out;
    }
}
