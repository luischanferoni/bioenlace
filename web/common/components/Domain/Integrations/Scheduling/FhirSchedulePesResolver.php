<?php

namespace common\components\Domain\Integrations\Scheduling;

use common\components\Domain\Person\Service\PersonCuilService;
use common\models\Efector;
use common\models\Integration\IntegrationScheduleLink;
use common\models\ProfesionalEfectorServicio;

/**
 * Resolución Schedule HAPI → PES (catálogo verificado + compuesto fail-closed).
 */
final class FhirSchedulePesResolver
{
    public const TRUST_VERIFIED = 'verified';
    public const TRUST_PROVISIONAL = 'provisional';
    public const TRUST_UNRESOLVED = 'unresolved';
    public const TRUST_STALE = 'stale';

    public function __construct(
        private ?FhirHealthcareServiceCodeCatalog $serviceCatalog = null
    ) {
        $this->serviceCatalog = $serviceCatalog ?? new FhirHealthcareServiceCodeCatalog();
    }

    /**
     * @return array{
     *   trust: string,
     *   id_profesional_efector_servicio: int|null,
     *   reason: string|null
     * }
     */
    public function resolve(
        string $sourceSystem,
        string $externalScheduleId,
        ScheduleActorSet $actors
    ): array {
        $sourceSystem = trim($sourceSystem);
        $externalScheduleId = trim($externalScheduleId);
        if ($sourceSystem === '' || $externalScheduleId === '') {
            return $this->unresolved('Schedule sin identificador de origen.');
        }

        $link = IntegrationScheduleLink::findVerified($sourceSystem, $externalScheduleId);
        if ($link !== null) {
            $fingerprint = $this->fingerprint($actors);
            if ($link->actor_fingerprint !== null && $link->actor_fingerprint !== '' && $link->actor_fingerprint !== $fingerprint) {
                return [
                    'trust' => self::TRUST_STALE,
                    'id_profesional_efector_servicio' => null,
                    'reason' => 'Los actores FHIR del Schedule divergen del vínculo verificado.',
                ];
            }

            return [
                'trust' => self::TRUST_VERIFIED,
                'id_profesional_efector_servicio' => (int) $link->id_profesional_efector_servicio,
                'reason' => null,
            ];
        }

        $composite = $this->resolveComposite($actors, $sourceSystem);
        if ($composite['id_profesional_efector_servicio'] === null) {
            return $this->unresolved($composite['reason'] ?? 'No se pudo resolver PES.');
        }

        return [
            'trust' => self::TRUST_PROVISIONAL,
            'id_profesional_efector_servicio' => $composite['id_profesional_efector_servicio'],
            'reason' => 'Resolver compuesto único; requiere verificación en catálogo Schedule.',
        ];
    }

    public function fingerprint(ScheduleActorSet $actors): string
    {
        $parts = [
            'cuil:' . PersonCuilService::normalize($actors->practitionerCuil),
            'dni:' . preg_replace('/\D+/', '', $actors->practitionerDni) ?? '',
            'sisa:' . trim($actors->locationSisa),
            'svc:' . trim($actors->serviceCodeSystem) . '|' . trim($actors->serviceCodeValue),
        ];
        sort($parts);

        return hash('sha256', implode(';', $parts));
    }

    /**
     * @return array{id_profesional_efector_servicio: int|null, reason: string|null}
     */
    private function resolveComposite(ScheduleActorSet $actors, string $sourceSystem): array
    {
        $idPersona = 0;
        if ($actors->practitionerCuil !== '') {
            $persona = PersonCuilService::findUniquePersonaByCuil($actors->practitionerCuil);
            if ($persona === null) {
                return ['id_profesional_efector_servicio' => null, 'reason' => 'CUIL de Practitioner sin persona única en Bioenlace.'];
            }
            $idPersona = (int) $persona->id_persona;
        } elseif ($actors->practitionerDni !== '') {
            return ['id_profesional_efector_servicio' => null, 'reason' => 'Se requiere CUIL en Practitioner (DNI solo es ambiguo).'];
        } else {
            return ['id_profesional_efector_servicio' => null, 'reason' => 'Falta identificador de Practitioner.'];
        }

        $sisa = trim($actors->locationSisa);
        if ($sisa === '') {
            return ['id_profesional_efector_servicio' => null, 'reason' => 'Falta código SISA en Location.'];
        }

        $efectores = Efector::find()->where(['codigo_sisa' => $sisa])->limit(2)->all();
        if (count($efectores) !== 1) {
            return ['id_profesional_efector_servicio' => null, 'reason' => 'SISA sin efector único en Bioenlace.'];
        }
        $idEfector = (int) $efectores[0]->id_efector;

        $idServicio = $this->serviceCatalog->resolveIdServicio(
            $actors->serviceCodeSystem,
            $actors->serviceCodeValue,
            $idEfector,
            $sourceSystem
        );
        if ($idServicio === null) {
            return ['id_profesional_efector_servicio' => null, 'reason' => 'Código de servicio FHIR sin mapeo único en catálogo.'];
        }

        $pes = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio($idPersona, $idEfector, $idServicio);
        if ($pes === null) {
            return ['id_profesional_efector_servicio' => null, 'reason' => 'No existe PES activo para la terna resuelta.'];
        }

        return ['id_profesional_efector_servicio' => (int) $pes->id, 'reason' => null];
    }

    /**
     * @return array{trust: string, id_profesional_efector_servicio: null, reason: string}
     */
    private function unresolved(string $reason): array
    {
        return [
            'trust' => self::TRUST_UNRESOLVED,
            'id_profesional_efector_servicio' => null,
            'reason' => $reason,
        ];
    }
}
