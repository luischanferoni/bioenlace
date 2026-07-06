<?php

namespace common\components\Domain\Integrations\Scheduling\Service;

use common\components\Domain\Integrations\Scheduling\FhirScheduleActorExtractor;
use common\components\Domain\Integrations\Scheduling\FhirSchedulePesResolver;
use common\components\Domain\Integrations\Scheduling\ScheduleActorSet;
use common\components\Domain\Integrations\Scheduling\Mapper\FhirAppointmentStatusMapper;
use common\components\Domain\Integrations\Scheduling\Dto\FhirAppointmentInboundDto;
use common\models\Integration\IntegrationScheduleLink;
use common\models\ProfesionalEfectorServicio;
use common\models\Scheduling\Turno;
use Yii;

/**
 * Alta/actualización de vínculos Schedule → PES verificados.
 */
final class IntegrationScheduleLinkService
{
    public function __construct(
        private ?FhirSchedulePesResolver $resolver = null
    ) {
        $this->resolver = $resolver ?? new FhirSchedulePesResolver();
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(
        string $sourceSystem,
        string $externalScheduleId,
        int $idPes,
        ScheduleActorSet $actors,
        int $verifiedByUserId
    ): array {
        $sourceSystem = trim($sourceSystem);
        $externalScheduleId = trim($externalScheduleId);
        if ($sourceSystem === '' || $externalScheduleId === '') {
            throw new \InvalidArgumentException('Schedule externo inválido.');
        }
        if ($idPes <= 0) {
            throw new \InvalidArgumentException('PES inválido.');
        }

        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null) {
            throw new \InvalidArgumentException('PES inexistente o inactivo.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $fingerprint = $this->resolver->fingerprint($actors);

        $row = IntegrationScheduleLink::find()
            ->where(['source_system' => $sourceSystem, 'external_schedule_id' => $externalScheduleId])
            ->one();
        if ($row === null) {
            $row = new IntegrationScheduleLink();
            $row->source_system = $sourceSystem;
            $row->external_schedule_id = $externalScheduleId;
            $row->created_at = $now;
        }

        $row->id_profesional_efector_servicio = $idPes;
        $row->resolution_method = IntegrationScheduleLink::METHOD_MANUAL;
        $row->actor_fingerprint = $fingerprint;
        $row->status = IntegrationScheduleLink::STATUS_VERIFIED;
        $row->verified_at = $now;
        $row->verified_by_user_id = $verifiedByUserId > 0 ? $verifiedByUserId : null;
        $row->updated_at = $now;

        if (!$row->save()) {
            throw new \RuntimeException('No se pudo guardar vínculo Schedule: ' . json_encode($row->getErrors()));
        }

        return [
            'id' => (int) $row->id,
            'status' => $row->status,
            'actor_fingerprint' => $fingerprint,
            'id_profesional_efector_servicio' => $idPes,
        ];
    }

    /**
     * @param array<string, mixed> $scheduleBundle
     * @return array<string, mixed>
     */
    public function previewFromScheduleBundle(string $sourceSystem, array $scheduleBundle): array
    {
        $schedules = \common\components\Domain\Integrations\Scheduling\Util\FhirBundleHelper::collectResources($scheduleBundle, 'Schedule');
        $schedule = $schedules[0] ?? null;
        if ($schedule === null) {
            throw new \InvalidArgumentException('Bundle sin Schedule.');
        }

        $scheduleId = \common\components\Domain\Integrations\Scheduling\Util\FhirBundleHelper::resourceId($schedule);
        $actors = (new FhirScheduleActorExtractor())->extractFromBundle($scheduleBundle);
        $resolution = $this->resolver->resolve($sourceSystem, $scheduleId, $actors);

        return [
            'external_schedule_id' => $scheduleId,
            'actors' => [
                'practitioner_cuil' => $actors->practitionerCuil,
                'location_sisa' => $actors->locationSisa,
                'service_code_system' => $actors->serviceCodeSystem,
                'service_code_value' => $actors->serviceCodeValue,
            ],
            'resolution' => $resolution,
            'fingerprint' => $this->resolver->fingerprint($actors),
        ];
    }
}
