<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Cancelación paciente y cierre staff con resolución declarada.
 */
final class ConsultaAsyncLifecycleService
{
    /**
     * @return array<string, mixed>
     */
    public function cancelarComoPaciente(int $encounterId, int $idPersona): array
    {
        $encounter = $this->requireAsyncEncounter($encounterId);
        if ((int) $encounter->subject_persona_id !== $idPersona) {
            throw new \InvalidArgumentException('No tenés permiso sobre esta solicitud.');
        }

        $policy = new ConsultaAsyncChatPolicyService();
        $resolved = $policy->resolveForEncounter($encounter, true);
        if (($resolved['acciones']['cancelar'] ?? false) !== true) {
            throw new \InvalidArgumentException(
                'Ya no podés retirar esta solicitud porque el equipo de salud la está atendiendo.'
            );
        }

        return $this->cerrarInterno(
            $encounter,
            'cancelada_paciente',
            null,
            $idPersona,
            false
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cerrarComoStaff(int $encounterId, string $resolutionCode, ?string $note = null): array
    {
        $encounter = $this->requireAsyncEncounter($encounterId);
        if (!ConsultaAsyncAccessService::staffCanAccessAsyncEncounter($encounter)) {
            throw new \InvalidArgumentException('No tenés permiso para cerrar esta solicitud.');
        }

        $status = strtolower(trim((string) $encounter->status));
        if (in_array($status, [EncounterStatus::FINISHED, EncounterStatus::CANCELLED], true)) {
            throw new \InvalidArgumentException('Esta solicitud ya está cerrada.');
        }

        return $this->cerrarInterno(
            $encounter,
            $resolutionCode,
            $note,
            (int) (Yii::$app->user->id ?? 0),
            false
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cerrarInterno(
        Encounter $encounter,
        string $resolutionCode,
        ?string $note,
        ?int $closedByUserId,
        bool $autoClose
    ): array {
        $catalog = new ConsultaAsyncChatPolicyCatalogService();
        $def = $catalog->resolution($resolutionCode);
        if ($def === null) {
            throw new \InvalidArgumentException('Resolución no válida.');
        }
        if (($def['require_note'] ?? false) === true && trim((string) $note) === '') {
            throw new \InvalidArgumentException('Indicá un motivo para esta resolución.');
        }

        $label = trim((string) ($def['label'] ?? $resolutionCode));
        $metaSvc = new ConsultaAsyncEncounterMetaService();
        $metaSvc->mergeAndSave($encounter, [
            'async_resolution' => [
                'code' => $resolutionCode,
                'label' => $label,
                'note' => trim((string) ($note ?? '')) ?: null,
                'closed_at' => date('c'),
                'closed_by_user_id' => $closedByUserId > 0 ? $closedByUserId : null,
                'auto_close' => $autoClose,
            ],
        ]);

        $newStatus = $resolutionCode === 'cancelada_paciente'
            ? EncounterStatus::CANCELLED
            : EncounterStatus::FINISHED;
        $encounter->status = $newStatus;
        if ($encounter->period_end === null || $encounter->period_end === '') {
            $encounter->period_end = date('Y-m-d H:i:s');
        }
        $encounter->save(false, ['status', 'period_end', 'updated_at', 'updated_by']);

        $sys = new ConsultaAsyncSystemMessageService();
        if ($resolutionCode === 'cancelada_paciente') {
            $msg = $catalog->cancelMessageExito();
            $sys->post($encounter, $msg !== '' ? $msg : $catalog->systemMessage('solicitud_cancelada'));
        } elseif ($resolutionCode === 'limite_conversacion') {
            $sys->postTemplate($encounter, 'limite_conversacion');
            $turnoHint = $catalog->systemMessage('limite_conversacion_turno');
            if ($turnoHint !== '') {
                $sys->post($encounter, $turnoHint);
            }
        } elseif (($def['notify_patient'] ?? true) === true) {
            $noteSuffix = trim((string) ($note ?? '')) !== '' ? ' ' . trim((string) $note) : '';
            $sys->postTemplate($encounter, 'solicitud_cerrada', [
                'resolution_label' => $label,
                'note_suffix' => $noteSuffix,
            ]);
        }

        return [
            'success' => true,
            'data' => [
                'encounter_id' => (int) $encounter->id,
                'status' => $newStatus,
                'resolution' => [
                    'code' => $resolutionCode,
                    'label' => $label,
                ],
            ],
        ];
    }

    private function requireAsyncEncounter(int $encounterId): Encounter
    {
        if ($encounterId <= 0) {
            throw new \InvalidArgumentException('Solicitud no válida.');
        }
        $encounter = Encounter::findOne([
            'id' => $encounterId,
            'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
            'deleted_at' => null,
        ]);
        if ($encounter === null) {
            throw new \InvalidArgumentException('Solicitud no encontrada.');
        }

        return $encounter;
    }
}
