<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\models\Clinical\Encounter;
use common\models\ConsultaChatMessage;

/**
 * Política de composer, cancelación y límites conversacionales (metadata-driven).
 */
final class ConsultaAsyncChatPolicyService
{
    /** @var list<string> */
    private const OPEN_STATUSES = [
        EncounterStatus::PLANNED,
        EncounterStatus::IN_PROGRESS,
        EncounterStatus::ON_HOLD,
    ];

    /**
     * @return array<string, mixed>
     */
    public function resolveForEncounter(Encounter $encounter, bool $viewerEsPaciente): array
    {
        $metaSvc = new ConsultaAsyncEncounterMetaService();
        $meta = $metaSvc->fromEncounter($encounter);
        $catalog = new ConsultaAsyncChatPolicyCatalogService();
        $sys = new ConsultaAsyncSystemMessageService();

        $status = strtolower(trim((string) $encounter->status));
        $cerrada = in_array($status, [EncounterStatus::FINISHED, EncounterStatus::CANCELLED], true);
        $structured = $metaSvc->isStructuredMedicacion($meta);
        $composerHint = $structured
            ? ($viewerEsPaciente
                ? $catalog->composerHintStructured()
                : $catalog->staffComposerHintStructured())
            : '';

        $patientCount = $sys->countMensajesPaciente((int) $encounter->id);
        $staffResponded = $sys->tieneRespuestaStaff((int) $encounter->id);
        $limits = $catalog->limitsConversational();
        $windowDays = max(1, (int) ($limits['window_days'] ?? 7));
        $windowExpired = $this->windowExpired($encounter, $windowDays);

        $composerEnabled = !$cerrada && !$windowExpired;
        if ($composerEnabled && $viewerEsPaciente) {
            if ($structured) {
                $composerEnabled = false;
            } elseif ($status === EncounterStatus::PLANNED && !$staffResponded) {
                $maxPlanned = max(1, (int) ($limits['max_patient_messages_while_planned_without_staff'] ?? 3));
                if ($patientCount >= $maxPlanned) {
                    $composerEnabled = false;
                    $composerHint = $catalog->systemMessage('limite_conversacion');
                }
            } else {
                $maxTotal = max(1, (int) ($limits['max_patient_messages_total'] ?? 20));
                if ($patientCount >= $maxTotal) {
                    $composerEnabled = false;
                    $composerHint = $catalog->systemMessage('limite_conversacion');
                }
            }
        } elseif ($composerEnabled && !$viewerEsPaciente) {
            $staffComposerOk = $structured
                ? $catalog->staffComposerStructured()
                : $catalog->staffComposerConversational();
            if (!$staffComposerOk) {
                $composerEnabled = false;
                if ($composerHint === '') {
                    $composerHint = $catalog->staffComposerHintStructured();
                }
            }
        }

        if ($cerrada) {
            $composerHint = $this->hintEncuentroCerrado($meta, $catalog);
        }

        $resolution = $meta['async_resolution'] ?? null;
        $resolutionCode = is_array($resolution) ? trim((string) ($resolution['code'] ?? '')) : '';

        $uploadTypes = [];
        $uploadEnabled = false;
        if ($composerEnabled) {
            if ($viewerEsPaciente) {
                if (!$structured) {
                    $uploadTypes = $catalog->allowedUploadMessageTypesForPatient();
                    $uploadEnabled = $uploadTypes !== [];
                }
            } else {
                $uploadTypes = $catalog->allowedUploadMessageTypes();
                $uploadEnabled = $uploadTypes !== [];
            }
        }

        return [
            'conversation_mode' => $structured ? 'structured' : 'conversational',
            'composer' => [
                'enabled' => $composerEnabled,
                'upload_enabled' => $uploadEnabled,
                'upload_types' => $uploadEnabled ? $uploadTypes : [],
                'hint' => $composerHint,
            ],
            'encounter_status' => $status,
            'encounter_closed' => $cerrada,
            'patient_message_count' => $patientCount,
            'staff_has_replied' => $staffResponded,
            'window_expired' => $windowExpired,
            'suggest_turno' => $resolutionCode !== '' && $this->resolutionSuggestsTurno($resolutionCode, $catalog),
            'resolution' => is_array($resolution) ? $resolution : null,
            'acciones' => [
                'cancelar' => $viewerEsPaciente && $this->puedeCancelarPaciente($encounter, $meta, $sys),
                'cerrar' => !$viewerEsPaciente && !$cerrada && $this->puedeCerrarStaff($encounter),
            ],
            'resoluciones_disponibles' => $viewerEsPaciente ? [] : $this->resolucionesStaff($meta, $catalog),
        ];
    }

    public function assertPatientCanSend(Encounter $encounter): void
    {
        $this->assertComposerCanSend($encounter, true);
    }

    public function assertStaffCanSend(Encounter $encounter): void
    {
        $this->assertComposerCanSend($encounter, false);
    }

    private function assertComposerCanSend(Encounter $encounter, bool $viewerEsPaciente): void
    {
        $policy = $this->resolveForEncounter($encounter, $viewerEsPaciente);
        if ($policy['composer']['enabled'] === true) {
            return;
        }
        $hint = trim((string) ($policy['composer']['hint'] ?? ''));
        throw new \InvalidArgumentException(
            $hint !== '' ? $hint : 'No podés enviar más mensajes en esta consulta.'
        );
    }

    /**
     * Tras mensaje paciente: auto-cierre si supera límites.
     */
    public function maybeAutoCloseAfterPatientMessage(Encounter $encounter): bool
    {
        if ($encounter->parent_type !== Encounter::PARENT_SOLICITUD_ASYNC) {
            return false;
        }
        $status = strtolower(trim((string) $encounter->status));
        if (!in_array($status, self::OPEN_STATUSES, true)) {
            return false;
        }

        $metaSvc = new ConsultaAsyncEncounterMetaService();
        if ($metaSvc->isStructuredMedicacion($metaSvc->fromEncounter($encounter))) {
            return false;
        }

        $policy = $this->resolveForEncounter($encounter, true);
        if ($policy['composer']['enabled'] === true && !$policy['window_expired']) {
            return false;
        }

        $limits = (new ConsultaAsyncChatPolicyCatalogService())->limitsConversational();
        $code = trim((string) ($limits['auto_close_resolution'] ?? 'limite_conversacion'));

        (new ConsultaAsyncLifecycleService())->cerrarInterno(
            $encounter,
            $code,
            null,
            null,
            true
        );

        return true;
    }

    private function windowExpired(Encounter $encounter, int $windowDays): bool
    {
        $createdTs = strtotime((string) $encounter->created_at) ?: time();
        $deadline = $createdTs + ($windowDays * 86400);

        return time() > $deadline;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function puedeCancelarPaciente(
        Encounter $encounter,
        array $meta,
        ConsultaAsyncSystemMessageService $sys
    ): bool {
        $cfg = (new ConsultaAsyncChatPolicyCatalogService())->cancelConfig();
        $allowed = $cfg['allowed_statuses'] ?? [EncounterStatus::PLANNED];
        if (!is_array($allowed)) {
            $allowed = [EncounterStatus::PLANNED];
        }
        $status = strtolower(trim((string) $encounter->status));
        if (!in_array($status, array_map('strval', $allowed), true)) {
            return false;
        }
        if (($cfg['require_no_staff_assigned'] ?? true) === true) {
            if ((int) ($encounter->id_profesional_efector_servicio ?? 0) > 0) {
                return false;
            }
        }
        if (($cfg['require_no_staff_message'] ?? true) === true && $sys->tieneRespuestaStaff((int) $encounter->id)) {
            return false;
        }

        return $encounter->parent_type === Encounter::PARENT_SOLICITUD_ASYNC;
    }

    private function puedeCerrarStaff(Encounter $encounter): bool
    {
        return $encounter->parent_type === Encounter::PARENT_SOLICITUD_ASYNC
            && ConsultaAsyncAccessService::staffCanAccessAsyncEncounter($encounter);
    }

    /**
     * Map code => { label, require_note } para CTAs de cierre staff.
     *
     * @param array<string, mixed> $meta
     * @return array<string, array{label: string, require_note: bool}>
     */
    private function resolucionesStaff(array $meta, ConsultaAsyncChatPolicyCatalogService $catalog): array
    {
        $codes = [];
        $op = trim((string) ($meta['medicacion_operacion'] ?? ''));
        if ($op === ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION) {
            $codes = ['medicacion_renovada', 'medicacion_no_indicada', 'requiere_control_presencial'];
        } elseif ($op === ConsultasSeguimientoIntakeService::MEDICACION_OP_AJUSTE) {
            $codes = ['medicacion_ajustada', 'medicacion_no_indicada', 'requiere_control_presencial'];
        } else {
            $codes = ['consulta_resuelta', 'requiere_control_presencial'];
        }

        $out = [];
        foreach ($codes as $code) {
            $def = $catalog->resolution($code);
            if ($def === null) {
                continue;
            }
            $label = trim((string) ($def['label'] ?? $code));
            if ($label === '') {
                continue;
            }
            $out[$code] = [
                'label' => $label,
                'require_note' => ($def['require_note'] ?? false) === true,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function hintEncuentroCerrado(array $meta, ConsultaAsyncChatPolicyCatalogService $catalog): string
    {
        $resolution = $meta['async_resolution'] ?? null;
        if (!is_array($resolution)) {
            return 'Esta consulta está cerrada.';
        }
        $label = trim((string) ($resolution['label'] ?? ''));
        $note = trim((string) ($resolution['note'] ?? ''));
        $parts = [];
        if ($label !== '') {
            $parts[] = $label;
        }
        if ($note !== '') {
            $parts[] = $note;
        }
        $base = $parts !== [] ? implode('. ', $parts) : 'Esta consulta está cerrada.';
        $code = trim((string) ($resolution['code'] ?? ''));
        if ($this->resolutionSuggestsTurno($code, $catalog)) {
            $turnoHint = $catalog->systemMessage('limite_conversacion_turno');
            if ($turnoHint !== '') {
                $base .= ' ' . $turnoHint;
            }
        }

        return $base;
    }

    private function resolutionSuggestsTurno(string $code, ConsultaAsyncChatPolicyCatalogService $catalog): bool
    {
        $def = $catalog->resolution($code);

        return is_array($def) && ($def['suggest_turno'] ?? false) === true;
    }
}
