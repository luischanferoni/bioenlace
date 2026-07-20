<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;

/**
 * Push FCM del lifecycle de consulta async (plantillas en metadata).
 */
final class ConsultaAsyncPushNotifier
{
    public function notifyTomadaPatient(Encounter $encounter): void
    {
        $idPersona = (int) ($encounter->subject_persona_id ?? 0);
        if ($idPersona <= 0) {
            return;
        }
        $tpl = $this->tpl('tomada_patient', $encounter);
        $this->sendPatient(
            $idPersona,
            $encounter,
            PushNotificationTypes::CONSULTA_ASYNC_TOMADA_PATIENT,
            'async-tomada',
            $tpl['title'],
            $tpl['body']
        );
    }

    public function notifyCerradaPatient(Encounter $encounter, string $resolutionLabel): void
    {
        $idPersona = (int) ($encounter->subject_persona_id ?? 0);
        if ($idPersona <= 0) {
            return;
        }
        $replace = $this->baseReplace($encounter);
        $replace['{{resolution_label}}'] = $resolutionLabel !== '' ? $resolutionLabel : 'Consulta finalizada';
        $tpl = (new ConsultaAsyncPushCatalogService())->event('cerrada_patient', $replace);
        $this->sendPatient(
            $idPersona,
            $encounter,
            PushNotificationTypes::CONSULTA_ASYNC_CERRADA_PATIENT,
            'async-cerrada',
            $tpl['title'],
            $tpl['body']
        );
    }

    public function notifyLimiteConversacionPatient(Encounter $encounter): void
    {
        $idPersona = (int) ($encounter->subject_persona_id ?? 0);
        if ($idPersona <= 0) {
            return;
        }
        $tpl = $this->tpl('limite_conversacion_patient', $encounter);
        $this->sendPatient(
            $idPersona,
            $encounter,
            PushNotificationTypes::CONSULTA_ASYNC_LIMITE_CONVERSACION_PATIENT,
            'async-limite',
            $tpl['title'],
            $tpl['body']
        );
    }

    public function notifyRespuestaStaffPatient(Encounter $encounter): void
    {
        $idPersona = (int) ($encounter->subject_persona_id ?? 0);
        if ($idPersona <= 0) {
            return;
        }
        $tpl = $this->tpl('respuesta_staff_patient', $encounter);
        $this->sendPatient(
            $idPersona,
            $encounter,
            PushNotificationTypes::CONSULTA_ASYNC_RESPUESTA_STAFF_PATIENT,
            'async-respuesta',
            $tpl['title'],
            $tpl['body']
        );
    }

    public function notifyNuevaSolicitudStaff(Encounter $encounter): void
    {
        $tpl = $this->tpl('nueva_solicitud_staff', $encounter);
        $this->sendStaffBandeja(
            $encounter,
            PushNotificationTypes::CONSULTA_ASYNC_NUEVA_SOLICITUD_STAFF,
            'async-nueva',
            $tpl['title'],
            $tpl['body']
        );
    }

    public function notifyMensajePacienteStaff(Encounter $encounter): void
    {
        $tpl = $this->tpl('mensaje_paciente_staff', $encounter);
        $this->sendStaffBandeja(
            $encounter,
            PushNotificationTypes::CONSULTA_ASYNC_MENSAJE_PACIENTE_STAFF,
            'async-msg-paciente',
            $tpl['title'],
            $tpl['body']
        );
    }

    public function notifyCanceladaStaff(Encounter $encounter): void
    {
        $tpl = $this->tpl('cancelada_staff', $encounter);
        $this->sendStaffBandeja(
            $encounter,
            PushNotificationTypes::CONSULTA_ASYNC_CANCELADA_STAFF,
            'async-cancelada',
            $tpl['title'],
            $tpl['body']
        );
    }

    /**
     * @return array{title: string, body: string}
     */
    private function tpl(string $eventKey, Encounter $encounter): array
    {
        return (new ConsultaAsyncPushCatalogService())->event($eventKey, $this->baseReplace($encounter));
    }

    /**
     * @return array<string, string>
     */
    private function baseReplace(Encounter $encounter): array
    {
        $subject = $encounter->subject;
        $paciente = $subject
            ? $subject->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';
        $serviceId = (int) ($encounter->service_id ?? 0);
        $servicio = $serviceId > 0 ? Servicio::findOne($serviceId) : null;

        return [
            '{{paciente}}' => $paciente,
            '{{servicio}}' => $servicio ? (string) $servicio->nombre : 'Servicio',
        ];
    }

    private function sendPatient(
        int $idPersona,
        Encounter $encounter,
        string $type,
        string $idempotencyPrefix,
        string $title,
        string $body
    ): void {
        (new PushNotificationSender())->sendToPersona(
            $idPersona,
            [
                'type' => $type,
                'encounter_id' => (string) $encounter->id,
            ],
            $title,
            $body,
            true,
            [
                'idempotency_key' => $idempotencyPrefix . ':' . (int) $encounter->id,
            ]
        );
    }

    private function sendStaffBandeja(
        Encounter $encounter,
        string $type,
        string $idempotencyPrefix,
        string $title,
        string $body
    ): void {
        $serviceId = (int) ($encounter->service_id ?? 0);
        $idEfector = (int) ($encounter->efector_id ?? 0);
        $staffPersonas = $this->staffPersonaIdsForServicio($serviceId, $idEfector);
        if ($staffPersonas === []) {
            return;
        }

        $push = new PushNotificationSender();
        foreach ($staffPersonas as $idPersona) {
            $push->sendToPersona(
                $idPersona,
                [
                    'type' => $type,
                    'encounter_id' => (string) $encounter->id,
                ],
                $title,
                $body,
                true,
                [
                    'idempotency_key' => $idempotencyPrefix . ':' . (int) $encounter->id . ':' . $idPersona,
                ]
            );
        }
    }

    /**
     * @return list<int>
     */
    private function staffPersonaIdsForServicio(int $serviceId, int $idEfector): array
    {
        if ($serviceId <= 0 || $idEfector <= 0) {
            return [];
        }
        $rows = ProfesionalEfectorServicio::find()
            ->select('id_persona')
            ->where([
                'id_servicio' => $serviceId,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->column();
        $out = [];
        foreach ($rows as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }
}
