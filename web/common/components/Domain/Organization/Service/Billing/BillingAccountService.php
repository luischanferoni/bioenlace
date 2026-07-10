<?php

namespace common\components\Domain\Organization\Service\Billing;

use common\models\BillingAccount;
use common\models\BillingAccountEfector;
use common\models\BillingAccountEncounterEntitlement;
use common\models\Clinical\EncounterDefinition;
use common\models\Efector;

/**
 * CRUD de cuentas de licencia y membresías / filas de entitlement (sin HTTP).
 */
final class BillingAccountService
{
    /**
     * @param array{nombre: string, tipo: string, notas?: string|null, activo?: int, owner_user_id?: int|null} $data
     */
    public static function createAccount(array $data): BillingAccount
    {
        $model = new BillingAccount();
        $model->nombre = (string) ($data['nombre'] ?? '');
        $model->tipo = (string) ($data['tipo'] ?? BillingAccount::TIPO_EFECTOR);
        $model->notas = isset($data['notas']) ? (string) $data['notas'] : null;
        $model->activo = isset($data['activo']) ? (int) $data['activo'] : 1;
        if (array_key_exists('owner_user_id', $data)) {
            $model->owner_user_id = $data['owner_user_id'] !== null ? (int) $data['owner_user_id'] : null;
        }
        if (!$model->save()) {
            throw new \InvalidArgumentException('No se pudo crear la cuenta: ' . json_encode($model->getErrors()));
        }

        return $model;
    }

    /**
     * @param array{nombre?: string, tipo?: string, notas?: string|null, activo?: int} $data
     */
    public static function updateAccount(BillingAccount $model, array $data): BillingAccount
    {
        if (array_key_exists('nombre', $data)) {
            $model->nombre = (string) $data['nombre'];
        }
        if (array_key_exists('tipo', $data)) {
            $model->tipo = (string) $data['tipo'];
        }
        if (array_key_exists('notas', $data)) {
            $model->notas = $data['notas'] !== null ? (string) $data['notas'] : null;
        }
        if (array_key_exists('activo', $data)) {
            $model->activo = (int) $data['activo'];
        }
        if (!$model->save()) {
            throw new \InvalidArgumentException('No se pudo actualizar la cuenta: ' . json_encode($model->getErrors()));
        }

        return $model;
    }

    /**
     * @param string $rol BillingAccountEfector::ROL_POOL|ROL_AFILIADO
     */
    public static function attachEfector(
        int $idBillingAccount,
        int $idEfector,
        string $rol = BillingAccountEfector::ROL_POOL
    ): BillingAccountEfector {
        if ($idBillingAccount <= 0 || $idEfector <= 0) {
            throw new \InvalidArgumentException('Cuenta o efector inválido.');
        }
        $rol = strtoupper(trim($rol));
        if (!isset(BillingAccountEfector::rolOptions()[$rol])) {
            throw new \InvalidArgumentException('Rol de membresía inválido.');
        }
        if (Efector::findOne(['id_efector' => $idEfector]) === null) {
            throw new \InvalidArgumentException('Efector inexistente.');
        }
        if (BillingAccount::findOne(['id' => $idBillingAccount, 'deleted_at' => null]) === null) {
            throw new \InvalidArgumentException('Cuenta inexistente.');
        }

        if ($rol === BillingAccountEfector::ROL_POOL) {
            $otherPool = BillingAccountEfector::find()
                ->where([
                    'id_efector' => $idEfector,
                    'rol_membresia' => BillingAccountEfector::ROL_POOL,
                    'deleted_at' => null,
                ])
                ->andWhere(['!=', 'id_billing_account', $idBillingAccount])
                ->one();
            if ($otherPool !== null) {
                throw new \InvalidArgumentException(
                    'El efector ya tiene una cuenta de facturación (pool) en la cuenta #'
                    . (int) $otherPool->id_billing_account
                    . '. Quitá ese vínculo POOL o usá rol Afiliado en esta cuenta.'
                );
            }
        }

        $existing = BillingAccountEfector::find()
            ->where([
                'id_billing_account' => $idBillingAccount,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->one();
        if ($existing !== null) {
            if ((string) $existing->rol_membresia !== $rol) {
                return self::updateMembershipRole($idBillingAccount, $idEfector, $rol);
            }

            return $existing;
        }

        $row = new BillingAccountEfector();
        $row->id_billing_account = $idBillingAccount;
        $row->id_efector = $idEfector;
        $row->rol_membresia = $rol;
        if (!$row->save()) {
            throw new \InvalidArgumentException('No se pudo asociar el efector: ' . json_encode($row->getErrors()));
        }

        return $row;
    }

    public static function updateMembershipRole(
        int $idBillingAccount,
        int $idEfector,
        string $rol
    ): BillingAccountEfector {
        $rol = strtoupper(trim($rol));
        if (!isset(BillingAccountEfector::rolOptions()[$rol])) {
            throw new \InvalidArgumentException('Rol de membresía inválido.');
        }

        $row = BillingAccountEfector::find()
            ->where([
                'id_billing_account' => $idBillingAccount,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->one();
        if ($row === null) {
            throw new \InvalidArgumentException('Membresía inexistente.');
        }

        if ($rol === BillingAccountEfector::ROL_POOL) {
            $otherPool = BillingAccountEfector::find()
                ->where([
                    'id_efector' => $idEfector,
                    'rol_membresia' => BillingAccountEfector::ROL_POOL,
                    'deleted_at' => null,
                ])
                ->andWhere(['!=', 'id', $row->id])
                ->one();
            if ($otherPool !== null) {
                throw new \InvalidArgumentException(
                    'El efector ya consume pool en otra cuenta (#' . (int) $otherPool->id_billing_account . ').'
                );
            }
        }

        $row->rol_membresia = $rol;
        if (!$row->save(false, ['rol_membresia', 'updated_at'])) {
            throw new \InvalidArgumentException('No se pudo cambiar el rol.');
        }

        return $row;
    }

    public static function detachEfector(int $idBillingAccount, int $idEfector): void
    {
        $row = BillingAccountEfector::find()
            ->where([
                'id_billing_account' => $idBillingAccount,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->one();
        if ($row === null) {
            return;
        }
        $row->delete();
    }

    /**
     * @param array{max_pes?: int|null, dictado_incluido?: int|bool, videollamada_permitida?: int|bool, activo?: int} $data
     */
    public static function upsertEntitlement(int $idBillingAccount, string $encounterClass, array $data): BillingAccountEncounterEntitlement
    {
        $encounterClass = strtoupper(trim($encounterClass));
        if ($encounterClass === '' || !isset(EncounterDefinition::ENCOUNTER_CLASS[$encounterClass])) {
            throw new \InvalidArgumentException('Clase de encounter inválida.');
        }

        $row = BillingAccountEncounterEntitlement::find()
            ->where([
                'id_billing_account' => $idBillingAccount,
                'encounter_class' => $encounterClass,
                'deleted_at' => null,
            ])
            ->one();
        if ($row === null) {
            $row = new BillingAccountEncounterEntitlement();
            $row->id_billing_account = $idBillingAccount;
            $row->encounter_class = $encounterClass;
            $row->dictado_incluido = in_array($encounterClass, ['EMER', 'IMP'], true) ? 1 : 0;
            $row->videollamada_permitida = 0;
        }

        if (array_key_exists('max_pes', $data)) {
            $row->max_pes = $data['max_pes'] === null || $data['max_pes'] === ''
                ? null
                : (int) $data['max_pes'];
        }
        if (array_key_exists('dictado_incluido', $data)) {
            $row->dictado_incluido = (int) (bool) $data['dictado_incluido'];
        }
        if (array_key_exists('videollamada_permitida', $data)) {
            $row->videollamada_permitida = (int) (bool) $data['videollamada_permitida'];
        }
        if (array_key_exists('activo', $data)) {
            $row->activo = (int) $data['activo'];
        } else {
            $row->activo = 1;
        }

        if (!$row->save()) {
            throw new \InvalidArgumentException('No se pudo guardar el entitlement: ' . json_encode($row->getErrors()));
        }

        return $row;
    }

    public static function deactivateEntitlement(int $idBillingAccount, string $encounterClass): void
    {
        $row = BillingAccountEncounterEntitlement::find()
            ->where([
                'id_billing_account' => $idBillingAccount,
                'encounter_class' => strtoupper(trim($encounterClass)),
                'deleted_at' => null,
            ])
            ->one();
        if ($row === null) {
            return;
        }
        $row->delete();
    }
}
