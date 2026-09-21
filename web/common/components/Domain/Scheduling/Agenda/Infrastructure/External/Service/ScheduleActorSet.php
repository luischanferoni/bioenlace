<?php

namespace common\components\Domain\Scheduling\Agenda\Infrastructure\External\Service;

/**
 * Actores normalizados extraídos de un Schedule FHIR (contrato HAPI fase 0).
 */
final class ScheduleActorSet
{
    public function __construct(
        public readonly string $practitionerCuil = '',
        public readonly string $practitionerDni = '',
        public readonly string $locationSisa = '',
        public readonly string $serviceCodeSystem = '',
        public readonly string $serviceCodeValue = '',
    ) {
    }
}
