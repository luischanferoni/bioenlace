<?php

namespace common\components\Domain\Organization\Service\Entitlement;

use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use common\models\BillingAccountEncounterEntitlement;
use common\models\BillingAccountEfector;
use common\models\Clinical\EncounterDefinition;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use Yii;
use yii\db\Query;

/**
 * Licencia por cuenta (pool): clases, max_pes y downgrade diferido.
 *
 * El efector resuelve su billing_account; el cupo se comparte entre todos los miembros.
 */
final class EfectorEncounterEntitlementService
{
    private const CLASS_PRIORITY = ['AMB', 'EMER', 'IMP'];

    public static function resolveAccountIdForEfector(int $idEfector): ?int
    {
        if ($idEfector <= 0) {
            return null;
        }
        $id = (new Query())
            ->select(['id_billing_account'])
            ->from(BillingAccountEfector::tableName())
            ->where(['id_efector' => $idEfector, 'deleted_at' => null])
            ->scalar();

        return $id !== false && $id !== null ? (int) $id : null;
    }

    /**
     * @return list<int>
     */
    public static function memberEfectorIds(int $idBillingAccount): array
    {
        if ($idBillingAccount <= 0) {
            return [];
        }
        $ids = (new Query())
            ->select(['id_efector'])
            ->from(BillingAccountEfector::tableName())
            ->where(['id_billing_account' => $idBillingAccount, 'deleted_at' => null])
            ->column();

        return array_map('intval', $ids);
    }

    /**
     * @return list<string>
     */
    public static function allowedEncounterClasses(int $idEfector): array
    {
        $all = array_keys(EncounterDefinition::ENCOUNTER_CLASS);
        if ($idEfector <= 0) {
            return $all;
        }

        $accountId = self::resolveAccountIdForEfector($idEfector);
        if ($accountId === null) {
            return PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll()
                ? $all
                : [];
        }

        $rows = BillingAccountEncounterEntitlement::findActivasPorAccount($accountId);
        if ($rows === []) {
            return PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll()
                ? $all
                : [];
        }

        $codes = [];
        foreach ($rows as $row) {
            $code = (string) $row->encounter_class;
            if ($code !== '' && isset(EncounterDefinition::ENCOUNTER_CLASS[$code])) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    public static function isEncounterClassAllowed(int $idEfector, string $encounterClass): bool
    {
        if ($encounterClass === '') {
            return true;
        }

        return in_array($encounterClass, self::allowedEncounterClasses($idEfector), true);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertEncounterClassAllowed(int $idEfector, string $encounterClass): void
    {
        if ($encounterClass === '') {
            return;
        }
        if (!self::isEncounterClassAllowed($idEfector, $encounterClass)) {
            $label = EncounterDefinition::ENCOUNTER_CLASS[$encounterClass] ?? $encounterClass;
            throw new \InvalidArgumentException(
                'El tipo de atención «' . $label . '» no está contratado para este efector.'
            );
        }
    }

    public static function maxPesForClass(int $idEfector, string $encounterClass): ?int
    {
        $accountId = self::resolveAccountIdForEfector($idEfector);
        if ($accountId === null) {
            return null;
        }

        return self::maxPesForAccountClass($accountId, $encounterClass);
    }

    public static function maxPesForAccountClass(int $idBillingAccount, string $encounterClass): ?int
    {
        foreach (BillingAccountEncounterEntitlement::findActivasPorAccount($idBillingAccount) as $row) {
            if ((string) $row->encounter_class === $encounterClass) {
                return $row->max_pes !== null ? (int) $row->max_pes : null;
            }
        }

        return null;
    }

    /**
     * @return list<array{code: string, label: string, max_pes: int|null, pending_max_pes: int|null, pending_effective_on: string|null, used: int, dictado_incluido: bool, videollamada_permitida: bool}>
     */
    public static function contractSummary(int $idEfector): array
    {
        $accountId = self::resolveAccountIdForEfector($idEfector);
        if ($accountId === null) {
            return [];
        }

        return self::contractSummaryForAccount($accountId);
    }

    /**
     * @return list<array{code: string, label: string, max_pes: int|null, pending_max_pes: int|null, pending_effective_on: string|null, used: int, dictado_incluido: bool, videollamada_permitida: bool}>
     */
    public static function contractSummaryForAccount(int $idBillingAccount): array
    {
        $out = [];
        foreach (BillingAccountEncounterEntitlement::findActivasPorAccount($idBillingAccount) as $row) {
            $code = (string) $row->encounter_class;
            $out[] = [
                'code' => $code,
                'label' => (string) (EncounterDefinition::ENCOUNTER_CLASS[$code] ?? $code),
                'max_pes' => $row->max_pes !== null ? (int) $row->max_pes : null,
                'pending_max_pes' => $row->pending_max_pes !== null ? (int) $row->pending_max_pes : null,
                'pending_effective_on' => $row->pending_effective_on !== null
                    ? (string) $row->pending_effective_on
                    : null,
                'used' => self::countBillablePersonasForAccount($idBillingAccount, $code),
                'dictado_incluido' => (bool) $row->dictado_incluido,
                'videollamada_permitida' => (bool) $row->videollamada_permitida,
            ];
        }

        return $out;
    }

    public static function isAdminEfectorServicio(int $idServicio): bool
    {
        if ($idServicio <= 0) {
            return false;
        }
        $item = (new Query())
            ->select(['item_name'])
            ->from(Servicio::tableName())
            ->where(['id_servicio' => $idServicio])
            ->scalar();

        return is_string($item) && strcasecmp(trim($item), 'AdminEfector') === 0;
    }

    public static function primaryClassForServicio(int $idServicio): ?string
    {
        if ($idServicio <= 0 || self::isAdminEfectorServicio($idServicio)) {
            return null;
        }

        $defs = (new Query())
            ->select(['encounter_class'])
            ->from(EncounterDefinition::tableName())
            ->where(['service_id' => $idServicio])
            ->column();
        $defs = array_values(array_unique(array_map('strval', $defs)));

        if ($defs === []) {
            $acepta = (new Query())
                ->select(['acepta_turnos'])
                ->from(Servicio::tableName())
                ->where(['id_servicio' => $idServicio])
                ->scalar();
            if (is_string($acepta) && strtoupper(trim($acepta)) === 'SI') {
                return 'AMB';
            }

            return null;
        }

        foreach (self::CLASS_PRIORITY as $code) {
            if (in_array($code, $defs, true)) {
                return $code;
            }
        }

        return $defs[0] ?? null;
    }

    /**
     * Uso en un efector (sin pool). Preferir countBillablePersonasForAccount para cupos.
     */
    public static function countBillablePersonas(int $idEfector, string $encounterClass): int
    {
        return count(self::billablePersonaIdsForEfectores([$idEfector], $encounterClass));
    }

    public static function countBillablePersonasForAccount(int $idBillingAccount, string $encounterClass): int
    {
        return count(self::billablePersonaIdsForEfectores(
            self::memberEfectorIds($idBillingAccount),
            $encounterClass
        ));
    }

    public static function personaIsBillableForClass(int $idEfector, int $idPersona, string $encounterClass): bool
    {
        $accountId = self::resolveAccountIdForEfector($idEfector);
        $efectorIds = $accountId !== null
            ? self::memberEfectorIds($accountId)
            : [$idEfector];

        return in_array($idPersona, self::billablePersonaIdsForEfectores($efectorIds, $encounterClass), true);
    }

    /**
     * @param list<int> $efectorIds
     * @return list<int>
     */
    private static function billablePersonaIdsForEfectores(array $efectorIds, string $encounterClass): array
    {
        $efectorIds = array_values(array_filter(array_map('intval', $efectorIds)));
        if ($efectorIds === [] || $encounterClass === '') {
            return [];
        }

        $rows = (new Query())
            ->select(['pes.id_persona', 'pes.id_servicio'])
            ->from(['pes' => ProfesionalEfectorServicio::tableName()])
            ->where(['pes.id_efector' => $efectorIds, 'pes.deleted_at' => null])
            ->all();

        $ids = [];
        foreach ($rows as $row) {
            $persona = (int) ($row['id_persona'] ?? 0);
            $servicio = (int) ($row['id_servicio'] ?? 0);
            if ($persona <= 0 || $servicio <= 0) {
                continue;
            }
            if (self::isAdminEfectorServicio($servicio)) {
                continue;
            }
            if (self::primaryClassForServicio($servicio) === $encounterClass) {
                $ids[$persona] = $persona;
            }
        }

        return array_values($ids);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertCanAddPes(int $idEfector, int $idPersona, int $idServicio): void
    {
        if ($idEfector <= 0 || $idPersona <= 0 || $idServicio <= 0) {
            return;
        }
        if (self::isAdminEfectorServicio($idServicio)) {
            return;
        }

        $accountId = self::resolveAccountIdForEfector($idEfector);
        if ($accountId === null) {
            return;
        }

        $rows = BillingAccountEncounterEntitlement::findActivasPorAccount($accountId);
        if ($rows === []) {
            return;
        }

        $class = self::primaryClassForServicio($idServicio);
        if ($class === null) {
            return;
        }

        if (!self::isEncounterClassAllowed($idEfector, $class)) {
            $label = EncounterDefinition::ENCOUNTER_CLASS[$class] ?? $class;
            throw new \InvalidArgumentException(
                'El tipo de atención «' . $label . '» no está contratado para este efector.'
            );
        }

        $max = self::maxPesForAccountClass($accountId, $class);
        if ($max === null) {
            return;
        }

        if (self::personaIsBillableForClass($idEfector, $idPersona, $class)) {
            return;
        }

        $used = self::countBillablePersonasForAccount($accountId, $class);
        if ($used >= $max) {
            $label = EncounterDefinition::ENCOUNTER_CLASS[$class] ?? $class;
            throw new \InvalidArgumentException(
                'Alcanzaste el máximo de profesionales contratados para «' . $label . '» ('
                . $max . '). Pedí una ampliación de licencia para agregar más.'
            );
        }
    }

    public static function syncPendingDowngradeForEfector(int $idEfector): void
    {
        $accountId = self::resolveAccountIdForEfector($idEfector);
        if ($accountId === null) {
            return;
        }
        self::syncPendingDowngradeForAccount($accountId);
    }

    public static function syncPendingDowngradeForAccount(int $idBillingAccount): void
    {
        if ($idBillingAccount <= 0) {
            return;
        }

        $nextPeriod = self::firstDayOfNextMonth();
        foreach (BillingAccountEncounterEntitlement::findActivasPorAccount($idBillingAccount) as $row) {
            $class = (string) $row->encounter_class;
            $max = $row->max_pes !== null ? (int) $row->max_pes : null;
            if ($max === null) {
                self::clearPending($row);
                continue;
            }

            $used = self::countBillablePersonasForAccount($idBillingAccount, $class);
            if ($used < $max) {
                $row->pending_max_pes = $used;
                $row->pending_effective_on = $nextPeriod;
                $row->save(false, ['pending_max_pes', 'pending_effective_on', 'updated_at']);
            } else {
                self::clearPending($row);
            }
        }
    }

    private static function clearPending(BillingAccountEncounterEntitlement $row): void
    {
        if ($row->pending_max_pes === null && $row->pending_effective_on === null) {
            return;
        }
        $row->pending_max_pes = null;
        $row->pending_effective_on = null;
        $row->save(false, ['pending_max_pes', 'pending_effective_on', 'updated_at']);
    }

    public static function firstDayOfNextMonth(?\DateTimeInterface $from = null): string
    {
        $dt = $from ? \DateTimeImmutable::createFromInterface($from) : new \DateTimeImmutable('today');

        return $dt->modify('first day of next month')->format('Y-m-d');
    }

    /**
     * @return int filas actualizadas
     */
    public static function applyPendingDowngrades(?string $onDate = null): int
    {
        $onDate = $onDate ?: (new \DateTimeImmutable('today'))->format('Y-m-d');
        $rows = BillingAccountEncounterEntitlement::find()
            ->where(['activo' => 1, 'deleted_at' => null])
            ->andWhere(['not', ['pending_max_pes' => null]])
            ->andWhere(['not', ['pending_effective_on' => null]])
            ->andWhere(['<=', 'pending_effective_on', $onDate])
            ->all();

        $n = 0;
        foreach ($rows as $row) {
            $row->max_pes = (int) $row->pending_max_pes;
            $row->pending_max_pes = null;
            $row->pending_effective_on = null;
            if ($row->save(false, ['max_pes', 'pending_max_pes', 'pending_effective_on', 'updated_at'])) {
                $n++;
                Yii::info(
                    sprintf(
                        'Entitlement downgrade aplicado account=%d class=%s max_pes=%d',
                        (int) $row->id_billing_account,
                        (string) $row->encounter_class,
                        (int) $row->max_pes
                    ),
                    __METHOD__
                );
            }
        }

        return $n;
    }
}
