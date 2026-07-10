<?php

namespace common\components\Domain\Organization\Service\Entitlement;

use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use common\models\Clinical\EncounterDefinition;
use common\models\EfectorEncounterEntitlement;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use Yii;
use yii\db\Query;

/**
 * Contrato comercial por efector: clases, tope de profesionales (PES) y downgrade diferido.
 *
 * Política:
 * - Alta: no superar max_pes de la clase primaria del servicio (mes en curso).
 * - Baja: no baja max_pes al instante; agenda pending_max_pes = uso actual, efectivo el 1º del mes siguiente.
 * - Si el uso vuelve al tope en el mismo mes, se cancela el pending.
 */
final class EfectorEncounterEntitlementService
{
    private const CLASS_PRIORITY = ['AMB', 'EMER', 'IMP'];

    /**
     * @return list<string>
     */
    public static function allowedEncounterClasses(int $idEfector): array
    {
        $all = array_keys(EncounterDefinition::ENCOUNTER_CLASS);
        if ($idEfector <= 0) {
            return $all;
        }

        $rows = EfectorEncounterEntitlement::findActivasPorEfector($idEfector);
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
        foreach (EfectorEncounterEntitlement::findActivasPorEfector($idEfector) as $row) {
            if ((string) $row->encounter_class === $encounterClass) {
                return $row->max_pes !== null ? (int) $row->max_pes : null;
            }
        }

        return null;
    }

    /**
     * @return list<array{code: string, label: string, max_pes: int|null, pending_max_pes: int|null, pending_effective_on: string|null}>
     */
    public static function contractSummary(int $idEfector): array
    {
        $out = [];
        foreach (EfectorEncounterEntitlement::findActivasPorEfector($idEfector) as $row) {
            $code = (string) $row->encounter_class;
            $out[] = [
                'code' => $code,
                'label' => (string) (EncounterDefinition::ENCOUNTER_CLASS[$code] ?? $code),
                'max_pes' => $row->max_pes !== null ? (int) $row->max_pes : null,
                'pending_max_pes' => $row->pending_max_pes !== null ? (int) $row->pending_max_pes : null,
                'pending_effective_on' => $row->pending_effective_on !== null
                    ? (string) $row->pending_effective_on
                    : null,
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

    /**
     * Clase comercial primaria del servicio (AMB → EMER → IMP según defs / turnos).
     */
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
     * Profesionales (personas distintas) con PES clínico activo atribuibles a la clase.
     */
    public static function countBillablePersonas(int $idEfector, string $encounterClass): int
    {
        if ($idEfector <= 0 || $encounterClass === '') {
            return 0;
        }

        $personas = self::billablePersonaIds($idEfector, $encounterClass);

        return count($personas);
    }

    public static function personaIsBillableForClass(int $idEfector, int $idPersona, string $encounterClass): bool
    {
        if ($idEfector <= 0 || $idPersona <= 0 || $encounterClass === '') {
            return false;
        }

        return in_array($idPersona, self::billablePersonaIds($idEfector, $encounterClass), true);
    }

    /**
     * @return list<int>
     */
    private static function billablePersonaIds(int $idEfector, string $encounterClass): array
    {
        $rows = (new Query())
            ->select(['pes.id_persona', 'pes.id_servicio'])
            ->from(['pes' => ProfesionalEfectorServicio::tableName()])
            ->where([
                'pes.id_efector' => $idEfector,
                'pes.deleted_at' => null,
            ])
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
     * Bloquea alta de PES si el efector ya alcanzó max_pes de la clase primaria del servicio.
     *
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

        $rows = EfectorEncounterEntitlement::findActivasPorEfector($idEfector);
        if ($rows === []) {
            // Sin contrato cargado: allow_all / sin tope.
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

        $max = self::maxPesForClass($idEfector, $class);
        if ($max === null) {
            return;
        }

        if (self::personaIsBillableForClass($idEfector, $idPersona, $class)) {
            return;
        }

        $used = self::countBillablePersonas($idEfector, $class);
        if ($used >= $max) {
            $label = EncounterDefinition::ENCOUNTER_CLASS[$class] ?? $class;
            throw new \InvalidArgumentException(
                'Alcanzaste el máximo de profesionales contratados para «' . $label . '» ('
                . $max . '). Pedí una ampliación de licencia para agregar más.'
            );
        }
    }

    /**
     * Tras baja de PES: si el uso bajó bajo max_pes, agenda downgrade al 1º del mes siguiente.
     * Si el uso volvió al tope, cancela el pending.
     */
    public static function syncPendingDowngradeForEfector(int $idEfector): void
    {
        if ($idEfector <= 0) {
            return;
        }

        $nextPeriod = self::firstDayOfNextMonth();
        foreach (EfectorEncounterEntitlement::findActivasPorEfector($idEfector) as $row) {
            $class = (string) $row->encounter_class;
            $max = $row->max_pes !== null ? (int) $row->max_pes : null;
            if ($max === null) {
                self::clearPending($row);
                continue;
            }

            $used = self::countBillablePersonas($idEfector, $class);
            if ($used < $max) {
                $row->pending_max_pes = $used;
                $row->pending_effective_on = $nextPeriod;
                $row->save(false, ['pending_max_pes', 'pending_effective_on', 'updated_at']);
            } else {
                self::clearPending($row);
            }
        }
    }

    private static function clearPending(EfectorEncounterEntitlement $row): void
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
     * Aplica pending_max_pes cuando pending_effective_on <= $onDate.
     *
     * @return int filas actualizadas
     */
    public static function applyPendingDowngrades(?string $onDate = null): int
    {
        $onDate = $onDate ?: (new \DateTimeImmutable('today'))->format('Y-m-d');
        $rows = EfectorEncounterEntitlement::find()
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
                        'Entitlement downgrade aplicado efector=%d class=%s max_pes=%d',
                        (int) $row->id_efector,
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
