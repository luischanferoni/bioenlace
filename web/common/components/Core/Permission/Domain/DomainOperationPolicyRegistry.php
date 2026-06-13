<?php

namespace common\components\Core\Permission\Domain;

use common\components\Clinical\Inpatient\Service\Authorization\ClinicalInternacionStaffAccessPolicy;
use common\components\Clinical\Service\Authorization\ClinicalEncounterAccessPolicy;
use common\components\Organization\Service\Authorization\OrganizationEfectorSesionPolicy;
use common\components\Organization\Service\Authorization\OrganizationPesEfectorPolicy;
use common\components\Organization\Service\Authorization\OrganizationPesOwnPolicy;
use common\components\Scheduling\Service\Authorization\TurnoCreateSubjectPolicy;
use common\components\Scheduling\Service\Authorization\TurnoStaffEfectorBelongsPolicy;
use common\components\Scheduling\Service\Authorization\TurnoSubjectOrRepresentativePolicy;

/**
 * Mapa estable handler_id → implementación (solo IDs, sin reglas de negocio).
 */
final class DomainOperationPolicyRegistry
{
    /** @var array<string, class-string<DomainOperationPolicyInterface>> */
    private const HANDLERS = [
        'turno.subject_or_representative' => TurnoSubjectOrRepresentativePolicy::class,
        'turno.staff_efector_belongs' => TurnoStaffEfectorBelongsPolicy::class,
        'turno.create_subject_or_representative' => TurnoCreateSubjectPolicy::class,
        'organization.efector_sesion' => OrganizationEfectorSesionPolicy::class,
        'organization.pes_efector' => OrganizationPesEfectorPolicy::class,
        'organization.pes_own' => OrganizationPesOwnPolicy::class,
        'clinical.encounter_participant' => ClinicalEncounterAccessPolicy::class,
        'clinical.internacion_staff_access' => ClinicalInternacionStaffAccessPolicy::class,
    ];

    /** @var array<string, DomainOperationPolicyInterface> */
    private static array $instances = [];

    public static function get(string $handlerId): DomainOperationPolicyInterface
    {
        $handlerId = trim($handlerId);
        if ($handlerId === '') {
            throw new \InvalidArgumentException('handler_id de política vacío.');
        }
        if (!isset(self::HANDLERS[$handlerId])) {
            throw new \InvalidArgumentException('Política de dominio desconocida: ' . $handlerId);
        }
        if (!isset(self::$instances[$handlerId])) {
            $class = self::HANDLERS[$handlerId];
            self::$instances[$handlerId] = new $class();
        }

        return self::$instances[$handlerId];
    }

    /** @return list<string> */
    public static function knownHandlerIds(): array
    {
        return array_keys(self::HANDLERS);
    }

    public static function resetForTests(): void
    {
        self::$instances = [];
    }
}
