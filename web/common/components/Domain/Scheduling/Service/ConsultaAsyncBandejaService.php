<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\models\Clinical\Encounter;
use common\models\ConsultaChatMessage;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use Yii;

/**
 * Bandeja staff y listado paciente para solicitudes async (SOLICITUD_ASYNC).
 */
final class ConsultaAsyncBandejaService
{
    /** @var list<string> */
    private const STATUS_BANDEJA = [
        EncounterStatus::PLANNED,
        EncounterStatus::IN_PROGRESS,
        EncounterStatus::ON_HOLD,
    ];

    /**
     * @return array<string, mixed>
     */
    public function listForStaffBandeja(): array
    {
        $catalog = new ConsultaAsyncBandejaCatalogService();
        $servicios = (new ConsultaAsyncStaffScopeService())->idServiciosAtendiblesEnEfector();
        if ($servicios === []) {
            return [
                'title' => $catalog->tituloSeccionStaff(),
                'items' => [],
                'total' => 0,
                'sla_incumplidos' => 0,
                'empty_message' => $catalog->mensajeVacioStaff(),
            ];
        }

        $encounters = Encounter::find()
            ->where([
                'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
            ])
            ->andWhere(['status' => self::STATUS_BANDEJA])
            ->andWhere(['service_id' => $servicios])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        $items = [];
        $encounterById = [];
        $slaIncumplidos = 0;
        foreach ($encounters as $encounter) {
            $item = $this->buildItem($encounter, true);
            if ($item === null) {
                continue;
            }
            $items[] = $item;
            $encounterById[(int) $encounter->id] = $encounter;
            if (!empty($item['sla']['incumplido'])) {
                $slaIncumplidos++;
            }
        }

        $result = [
            'title' => $catalog->tituloSeccionStaff(),
            'items' => $items,
            'total' => count($items),
            'sla_incumplidos' => $slaIncumplidos,
            'empty_message' => $catalog->mensajeVacioStaff(),
        ];

        return (new ConsultaAsyncBandejaPrioridadAgent())->applyToStaffBandeja($result, $encounterById);
    }

    /**
     * @return array<string, mixed>
     */
    public function listForPaciente(int $idPersona): array
    {
        $catalog = new ConsultaAsyncBandejaCatalogService();
        if ($idPersona <= 0) {
            return [
                'title' => $catalog->tituloSeccionPaciente(),
                'items' => [],
                'total' => 0,
                'empty_message' => $catalog->mensajeVacioPaciente(),
                'history' => [
                    'title' => $catalog->tituloHistorialPaciente(),
                    'items' => [],
                    'total' => 0,
                    'empty_message' => $catalog->mensajeVacioHistorialPaciente(),
                ],
            ];
        }

        $encounters = Encounter::find()
            ->where([
                'subject_persona_id' => $idPersona,
                'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
            ])
            ->andWhere(['status' => self::STATUS_BANDEJA])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($encounters as $encounter) {
            $item = $this->buildItem($encounter, false);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $historyLimit = $catalog->limiteHistorialPaciente();
        $historyEncounters = Encounter::find()
            ->where([
                'subject_persona_id' => $idPersona,
                'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
            ])
            ->andWhere(['status' => [EncounterStatus::FINISHED, EncounterStatus::CANCELLED]])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit($historyLimit)
            ->all();

        $history = [];
        foreach ($historyEncounters as $encounter) {
            $item = $this->buildItem($encounter, false);
            if ($item !== null) {
                $history[] = $item;
            }
        }

        return [
            'title' => $catalog->tituloSeccionPaciente(),
            'items' => $items,
            'total' => count($items),
            'empty_message' => $catalog->mensajeVacioPaciente(),
            'history' => [
                'title' => $catalog->tituloHistorialPaciente(),
                'items' => $history,
                'total' => count($history),
                'empty_message' => $catalog->mensajeVacioHistorialPaciente(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tomarComoStaff(int $encounterId): array
    {
        $encounter = Encounter::findOne([
            'id' => $encounterId,
            'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
            'deleted_at' => null,
        ]);
        if ($encounter === null) {
            throw new \InvalidArgumentException('Solicitud no encontrada.');
        }
        if ($encounter->status !== EncounterStatus::PLANNED) {
            throw new \InvalidArgumentException('Esta solicitud ya fue tomada o cerrada.');
        }

        $idPesAsignado = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        if ($idPesAsignado > 0 && !ConsultaAsyncAccessService::pesPerteneceAlUsuarioActual($idPesAsignado)) {
            throw new \InvalidArgumentException('La solicitud está asignada a otro profesional.');
        }

        if (!ConsultaAsyncAccessService::staffCanAccessAsyncEncounter($encounter)) {
            throw new \InvalidArgumentException('No tenés permiso para atender esta solicitud.');
        }

        $serviceId = (int) ($encounter->service_id ?? 0);
        $idPes = (new ConsultaAsyncStaffScopeService())->idPesSesionParaServicio($serviceId);
        if ($idPes <= 0) {
            throw new \InvalidArgumentException('No hay un servicio profesional válido en tu sesión para este caso.');
        }

        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null) {
            throw new \InvalidArgumentException('No se pudo resolver el profesional en el efector.');
        }

        $encounter->id_profesional_efector_servicio = $idPes;
        $encounter->efector_id = (int) $pes->id_efector;
        $encounter->status = EncounterStatus::IN_PROGRESS;
        if ($encounter->period_start === null || $encounter->period_start === '') {
            $encounter->period_start = date('Y-m-d H:i:s');
        }
        if (!$encounter->save(false, [
            'id_profesional_efector_servicio',
            'efector_id',
            'status',
            'period_start',
            'updated_at',
            'updated_by',
        ])) {
            throw new \RuntimeException('No se pudo asignar la solicitud.');
        }

        (new ConsultaAsyncSystemMessageService())->postTemplate($encounter, 'solicitud_tomada');

        try {
            (new ConsultaAsyncPushNotifier())->notifyTomadaPatient($encounter);
        } catch (\Throwable $e) {
            Yii::warning('Push async tomada: ' . $e->getMessage(), 'consulta-async-push');
        }

        return [
            'success' => true,
            'data' => [
                'encounter_id' => (int) $encounter->id,
                'id_profesional_efector_servicio' => $idPes,
            ],
            'message' => 'Solicitud asignada. Podés responder por mensaje.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildItem(Encounter $encounter, bool $staffView): ?array
    {
        if ($staffView && !ConsultaAsyncAccessService::staffCanAccessAsyncEncounter($encounter)) {
            return null;
        }

        $idPes = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        $esMio = $idPes > 0 && ConsultaAsyncAccessService::pesPerteneceAlUsuarioActual($idPes);
        if (
            $staffView
            && $encounter->status !== EncounterStatus::PLANNED
            && $idPes > 0
            && !$esMio
        ) {
            return null;
        }

        $catalog = new ConsultaAsyncBandejaCatalogService();
        $policyCatalog = new ConsultaAsyncChatPolicyCatalogService();
        $meta = $this->parseNote($encounter->note);
        $urgencyBand = isset($meta['urgency_band']) ? (string) $meta['urgency_band'] : null;
        $sla = $this->buildSla($encounter, $urgencyBand, $catalog);
        $idPersona = (int) ($encounter->subject_persona_id ?? 0);
        $intakeContext = (new ConsultaAsyncIntakeContextService())->buildFromMeta($meta, $idPersona);

        $serviceId = (int) ($encounter->service_id ?? 0);
        $servicio = $serviceId > 0 ? Servicio::findOne($serviceId) : null;

        $subject = $encounter->subject;
        $profesionalNombre = null;
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes !== null && $pes->persona) {
                $profesionalNombre = $pes->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N);
            }
        }

        $puedeTomar = $staffView
            && $encounter->status === EncounterStatus::PLANNED
            && $idPes === 0
            && ConsultaAsyncAccessService::staffPuedeTomar($encounter);
        $abrirChat = !$staffView
            || ($encounter->status !== EncounterStatus::PLANNED && $esMio);

        $resolution = $meta['async_resolution'] ?? null;
        $resolutionLabel = is_array($resolution)
            ? trim((string) ($resolution['label'] ?? ''))
            : '';

        return [
            'encounter_id' => (int) $encounter->id,
            'solicitud_tipo' => $this->solicitudTipoFromMeta($meta, $policyCatalog),
            'paciente' => [
                'id_persona' => (int) $encounter->subject_persona_id,
                'nombre_completo' => $subject ? $subject->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N) : 'Paciente',
            ],
            'servicio' => $servicio ? (string) $servicio->nombre : 'Servicio',
            'servicio_id' => $serviceId,
            'status' => (string) $encounter->status,
            'status_label' => $catalog->etiquetaEstado((string) $encounter->status),
            'created_at' => (string) $encounter->created_at,
            'reason_preview' => $this->previewText((string) ($encounter->reason_text ?? '')),
            'resolution_label' => $resolutionLabel !== '' ? $resolutionLabel : null,
            'urgency_band' => $urgencyBand,
            'intake_context' => $intakeContext,
            'sla' => $sla,
            'asignacion' => [
                'id_pes' => $idPes > 0 ? $idPes : null,
                'profesional' => $profesionalNombre,
                'es_mio' => $esMio,
            ],
            'acciones' => [
                'tomar' => $puedeTomar,
                'abrir_chat' => $abrirChat,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSla(Encounter $encounter, ?string $urgencyBand, ConsultaAsyncBandejaCatalogService $catalog): array
    {
        $horas = $catalog->horasSlaRespuesta($urgencyBand);
        $createdTs = strtotime((string) $encounter->created_at) ?: time();
        $venceTs = $createdTs + ($horas * 3600);
        $respondido = $this->tieneRespuestaStaff((int) $encounter->id)
            || $encounter->status !== EncounterStatus::PLANNED;
        $incumplido = !$respondido && time() > $venceTs;

        return [
            'horas_objetivo' => $horas,
            'vence_at' => date('Y-m-d H:i:s', $venceTs),
            'incumplido' => $incumplido,
            'respondido' => $respondido,
        ];
    }

    private function tieneRespuestaStaff(int $encounterId): bool
    {
        return ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['user_role' => ['medico', 'enfermeria']])
            ->exists();
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function solicitudTipoFromMeta(array $meta, ConsultaAsyncChatPolicyCatalogService $catalog): string
    {
        $op = trim((string) ($meta['medicacion_operacion'] ?? ''));
        if ($op !== '') {
            return $catalog->solicitudTipoLabel($op);
        }
        $necesidad = trim((string) ($meta['seguimiento_necesidad'] ?? ''));
        if ($necesidad !== '') {
            return $catalog->solicitudTipoLabel($necesidad);
        }
        $intake = trim((string) ($meta['intake_tipo'] ?? ''));

        return $catalog->solicitudTipoLabel($intake !== '' ? $intake : 'consulta_general');
    }

    /**
     * @return array<string, mixed>
     */
    private function parseNote(?string $note): array
    {
        if ($note === null || trim($note) === '') {
            return [];
        }
        $decoded = json_decode($note, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function previewText(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= 160) {
            return $text;
        }

        return mb_substr($text, 0, 157) . '…';
    }
}
