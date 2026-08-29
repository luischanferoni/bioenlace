<?php

namespace common\components\Domain\Organization\Service\Seed;

use common\components\Domain\Scheduling\Service\TurnoSlotClaimService;
use common\models\Clinical\Encounter;
use common\models\Emergency\GuardiaCircuitoEvent;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use common\models\InfraestructuraCama;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraSala;
use common\models\Person\Persona;
use common\models\Platform\DemoSandboxSession;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ProfesionalEfectorServicioAgendaVersion;
use common\models\Scheduling\Turno;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionHcama;
use common\models\User;
use Yii;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Query;

/**
 * Purga filas creadas por una sesión demo sandbox.
 *
 * Soft-purge: retiros / soft-delete + anonimización.
 * Hard-delete: elimina residuos de personas DemoPurged sin dejar hijos clínicos huérfanos
 * (tablas sin ON DELETE CASCADE según el esquema).
 */
final class DemoSandboxPurgeService
{
    private const APELLIDO_PURGED = 'DemoPurged';

    /**
     * Tablas hijas de encounter sin CASCADE confiable (o RESTRICT).
     * Se borran por `encounter_id` y/o `id_consulta` si la columna existe.
     *
     * @var list<string>
     */
    private const ENCOUNTER_CHILD_TABLES = [
        '{{%electronic_prescription}}',
        '{{%interaccion_chat_clinico}}',
        '{{%interaccion_motivos_consulta}}',
        '{{%encounter_capture_analysis}}',
        '{{%encounter_capture_audit}}',
        '{{%encounter_capture}}',
        '{{%encounter_patient_summary_publish_queue}}',
        '{{%atenciones_enfermeria}}',
        '{{%observation}}',
        '{{%allergy_intolerance}}',
        '{{%goal}}',
        '{{%care_followup_response}}',
        '{{%care_pack_job}}',
        '{{%agent_run}}',
        '{{%persona_antecedentes}}',
        '{{%practicas_personas}}',
        '{{%practicas_personas_viejo}}',
        '{{%personas_antecedentes_viejo}}',
        '{{%tension_arterial}}',
        '{{%valoracion_nutricional}}',
        '{{%odonto_consulta_persona}}',
        '{{%consultas_medicamentos_infusion_continua}}',
        '{{%consultas_medicamentos_internacion}}',
        '{{%sumar_autofacturacion_log}}',
        // FHIR con CASCADE: se listan por si el schema local no tiene FK.
        '{{%medication_request}}',
        '{{%medication_administration}}',
        '{{%service_request}}',
        '{{%procedure}}',
        '{{%clinical_condition}}',
        '{{%clinical_impression}}',
        '{{%device_request}}',
        '{{%nutrition_order}}',
        '{{%vision_prescription}}',
        '{{%care_assistance_response}}',
        '{{%care_encounter_pack}}',
        '{{%care_followup_touchpoint_queue}}',
        '{{%clinical_history_outbound_job}}',
        '{{%encounter_patient_summary}}',
        '{{%diagnostic_report}}',
        '{{%care_plan}}',
    ];

    /**
     * Filas clínicas ancladas a persona (subject / id_persona) sin CASCADE a personas.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const PERSONA_CLINICAL_TABLES = [
        ['{{%allergy_intolerance}}', 'subject_persona_id'],
        ['{{%clinical_condition}}', 'subject_persona_id'],
        ['{{%care_plan}}', 'subject_persona_id'],
        ['{{%diagnostic_report}}', 'subject_persona_id'],
        ['{{%electronic_prescription}}', 'subject_persona_id'],
        ['{{%episode_of_care}}', 'subject_persona_id'],
        ['{{%goal}}', 'subject_persona_id'],
        ['{{%observation}}', 'subject_persona_id'],
        ['{{%medication_request}}', 'subject_persona_id'],
        ['{{%service_request}}', 'subject_persona_id'],
        ['{{%procedure}}', 'subject_persona_id'],
        ['{{%device_request}}', 'subject_persona_id'],
        ['{{%nutrition_order}}', 'subject_persona_id'],
        ['{{%clinical_impression}}', 'subject_persona_id'],
        ['{{%care_assistance_response}}', 'subject_persona_id'],
        ['{{%care_encounter_pack}}', 'subject_persona_id'],
        ['{{%care_followup_response}}', 'subject_persona_id'],
        ['{{%care_followup_touchpoint_queue}}', 'subject_persona_id'],
        ['{{%care_pack_job}}', 'subject_persona_id'],
        ['{{%clinical_history_outbound_job}}', 'subject_persona_id'],
        ['{{%encounter_patient_summary}}', 'subject_persona_id'],
        ['{{%encounter_capture}}', 'subject_persona_id'],
        ['{{%encounter_capture_analysis}}', 'subject_persona_id'],
        ['{{%agent_run}}', 'subject_persona_id'],
        ['{{%atenciones_enfermeria}}', 'id_persona'],
        ['{{%practicas_personas}}', 'id_persona'],
        ['{{%tension_arterial}}', 'id_persona'],
        ['{{%valoracion_nutricional}}', 'id_persona'],
        ['{{%persona_antecedentes}}', 'id_persona'],
        ['{{%odonto_consulta_persona}}', 'id_persona'],
        ['{{%cirugia}}', 'id_persona'],
        ['{{%documentos_externos}}', 'id_persona'],
    ];

    /**
     * @return array{purged: bool, already: bool, errors: list<string>}
     */
    public function purgeSession(DemoSandboxSession $session): array
    {
        if ($session->isPurged()) {
            return ['purged' => false, 'already' => true, 'errors' => []];
        }

        $errors = [];
        $payload = $session->getSeedPayload();
        $now = date('Y-m-d H:i:s');

        $pacienteIds = $this->normalizeIds((array) ($payload['paciente_ids'] ?? []));

        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $turnoIds = $this->normalizeIds((array) ($payload['turno_ids'] ?? []));
            if ($pacienteIds !== []) {
                $extraTurnos = (new Query())
                    ->select('id_turnos')
                    ->from(Turno::tableName())
                    ->where(['id_persona' => $pacienteIds])
                    ->column($db);
                $turnoIds = $this->normalizeIds(array_merge($turnoIds, $extraTurnos));
            }
            foreach ($turnoIds as $idTurno) {
                try {
                    TurnoSlotClaimService::releaseForTurno($idTurno);
                } catch (\Throwable $e) {
                    $errors[] = 'slot ' . $idTurno . ': ' . $e->getMessage();
                }
                $turno = Turno::findOne($idTurno);
                if ($turno !== null) {
                    $turno->estado = Turno::ESTADO_CANCELADO;
                    $turno->estado_motivo = Turno::ESTADO_MOTIVO_CANCELADO_SISTEMA;
                    if ($turno->hasAttribute('deleted_at')) {
                        $turno->deleted_at = new Expression('NOW()');
                    }
                    $turno->save(false);
                }
            }

            $encounterIds = $this->normalizeIds(array_merge(
                (array) ($payload['encounter_ids'] ?? []),
                (array) ($payload['async_encounter_ids'] ?? [])
            ));
            if ($pacienteIds !== []) {
                $extraEncounters = (new Query())
                    ->select('id')
                    ->from(Encounter::tableName())
                    ->where(['subject_persona_id' => $pacienteIds])
                    ->andWhere(['deleted_at' => null])
                    ->column($db);
                $encounterIds = $this->normalizeIds(array_merge($encounterIds, $extraEncounters));
            }
            $this->softDeleteEncounters($encounterIds, $now, $errors);

            foreach ((array) ($payload['internacion_ids'] ?? []) as $idInternacion) {
                $idInternacion = (int) $idInternacion;
                if ($idInternacion <= 0) {
                    continue;
                }
                $internacion = SegNivelInternacion::findOne($idInternacion);
                if ($internacion === null) {
                    continue;
                }
                if (empty($internacion->fecha_fin)) {
                    $internacion->fecha_fin = date('d/m/Y');
                    $internacion->hora_fin = date('H:i');
                    $internacion->save(false);
                }
                SegNivelInternacionHcama::deleteAll(['id_internacion' => $idInternacion]);
            }

            foreach ((array) ($payload['cama_ids'] ?? []) as $idCama) {
                $idCama = (int) $idCama;
                if ($idCama <= 0) {
                    continue;
                }
                $cama = InfraestructuraCama::findOne($idCama);
                if ($cama !== null) {
                    $cama->delete();
                }
            }

            foreach ((array) ($payload['sala_ids'] ?? []) as $idSala) {
                $idSala = (int) $idSala;
                if ($idSala <= 0) {
                    continue;
                }
                $sala = InfraestructuraSala::findOne($idSala);
                if ($sala !== null) {
                    $sala->delete();
                }
            }

            foreach ((array) ($payload['piso_ids'] ?? []) as $idPiso) {
                $idPiso = (int) $idPiso;
                if ($idPiso <= 0) {
                    continue;
                }
                $piso = InfraestructuraPiso::findOne($idPiso);
                if ($piso !== null) {
                    $piso->delete();
                }
            }

            $guardiaIds = $this->normalizeIds((array) ($payload['guardia_ids'] ?? []));
            if ($pacienteIds !== []) {
                $extraGuardias = (new Query())
                    ->select('id')
                    ->from(Guardia::tableName())
                    ->where(['id_persona' => $pacienteIds])
                    ->column($db);
                $guardiaIds = $this->normalizeIds(array_merge($guardiaIds, $extraGuardias));
            }
            foreach ($guardiaIds as $idGuardia) {
                $g = Guardia::findIncludingDeleted()->where(['id' => $idGuardia])->one();
                if ($g !== null && $g->hasAttribute('deleted_at') && $g->deleted_at === null) {
                    $g->deleted_at = $now;
                    $g->save(false);
                }
            }

            $idAgenda = (int) ($payload['id_agenda'] ?? 0);
            if ($idAgenda > 0) {
                $agenda = ProfesionalEfectorServicioAgenda::findOne($idAgenda);
                if ($agenda !== null) {
                    if ($agenda->hasAttribute('deleted_at')) {
                        $agenda->deleted_at = $now;
                        $agenda->save(false);
                    } else {
                        $agenda->delete();
                    }
                }
            }

            $pes = ProfesionalEfectorServicio::findOne((int) $session->id_pes);
            if ($pes !== null && $pes->deleted_at === null) {
                $pes->deleted_at = $now;
                $pes->save(false);
            }

            foreach ($pacienteIds as $idPaciente) {
                $persona = Persona::findOne($idPaciente);
                if ($persona === null) {
                    continue;
                }
                $idUserPac = (int) ($persona->id_user ?? 0);
                if ($idUserPac > 0) {
                    $uPac = User::findOne($idUserPac);
                    if ($uPac !== null) {
                        $uPac->status = User::STATUS_INACTIVE;
                        $uPac->username = 'x_p_' . $session->id . '_' . substr((string) $uPac->username, 0, 36);
                        $uPac->email = 'purged_p_' . $session->id . '_' . $idPaciente . '@demo.bioenlace.local';
                        $uPac->save(false);
                    }
                }
                $persona->documento = $this->retireDocumento((string) $persona->documento, $idPaciente);
                $persona->apellido = self::APELLIDO_PURGED;
                $persona->id_user = null;
                $persona->save(false);
            }

            $staff = Persona::findOne((int) $session->id_persona);
            if ($staff !== null) {
                $staff->documento = $this->retireDocumento((string) $staff->documento, (int) $staff->id_persona);
                $staff->apellido = self::APELLIDO_PURGED;
                $staff->save(false);
            }

            $user = User::findOne((int) $session->id_user);
            if ($user !== null) {
                $user->status = User::STATUS_INACTIVE;
                $user->username = 'x_' . $session->id . '_' . substr((string) $user->username, 0, 40);
                $user->email = 'purged_' . $session->id . '@demo.bioenlace.local';
                $user->save(false);
            }

            $session->purged_at = $now;
            $session->save(false);

            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            Yii::error('demo sandbox purge: ' . $e->getMessage(), __METHOD__);

            return ['purged' => false, 'already' => false, 'errors' => [$e->getMessage()]];
        }

        return ['purged' => true, 'already' => false, 'errors' => $errors];
    }

    /**
     * @return array{scanned: int, purged: int, errors: list<string>}
     */
    public function purgeExpired(?string $now = null): array
    {
        $now = $now ?? date('Y-m-d H:i:s');
        /** @var DemoSandboxSession[] $rows */
        $rows = DemoSandboxSession::find()
            ->where(['purged_at' => null])
            ->andWhere(['<=', 'expires_at', $now])
            ->limit(100)
            ->all();

        $purged = 0;
        $errors = [];
        foreach ($rows as $row) {
            $result = $this->purgeSession($row);
            if ($result['purged']) {
                $purged++;
            }
            foreach ($result['errors'] as $err) {
                $errors[] = 'session ' . $row->id . ': ' . $err;
            }
        }

        return [
            'scanned' => count($rows),
            'purged' => $purged,
            'errors' => $errors,
        ];
    }

    /**
     * Hard-delete residuos de demos anonimizadas (apellido DemoPurged), sin huérfanos clínicos.
     *
     * @return array{
     *   personas: int,
     *   guardias: int,
     *   encounters: int,
     *   turnos: int,
     *   internaciones: int,
     *   pes: int,
     *   users: int,
     *   errors: list<string>
     * }
     */
    public function hardDeletePurgedResidues(): array
    {
        $errors = [];
        $counts = [
            'personas' => 0,
            'guardias' => 0,
            'encounters' => 0,
            'turnos' => 0,
            'internaciones' => 0,
            'pes' => 0,
            'users' => 0,
            'errors' => [],
        ];

        $personaIds = (new Query())
            ->select('id_persona')
            ->from(Persona::tableName())
            ->where(['apellido' => self::APELLIDO_PURGED])
            ->column(Yii::$app->db);
        $personaIds = $this->normalizeIds($personaIds);
        if ($personaIds === []) {
            return $counts;
        }

        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            // 1) Guardias (todas, no solo soft-deleted)
            $guardiaIds = (new Query())
                ->select('id')
                ->from(Guardia::tableName())
                ->where(['id_persona' => $personaIds])
                ->column($db);
            $guardiaIds = $this->normalizeIds($guardiaIds);
            if ($guardiaIds !== []) {
                $this->safeDelete($db, GuardiaCircuitoEvent::tableName(), ['guardia_id' => $guardiaIds], $errors);
                $this->safeDelete($db, GuardiaTriage::tableName(), ['guardia_id' => $guardiaIds], $errors);
                $counts['guardias'] = $this->safeDelete($db, Guardia::tableName(), ['id' => $guardiaIds], $errors);
            }

            // 2) Encounters + hijos (incl. async y los de atención sin soft-delete previo)
            $encounterIds = (new Query())
                ->select('id')
                ->from(Encounter::tableName())
                ->where(['subject_persona_id' => $personaIds])
                ->column($db);
            $encounterIds = $this->normalizeIds($encounterIds);
            if ($encounterIds !== []) {
                $this->hardDeleteEncounterChildren($db, $encounterIds, $errors);
                $counts['encounters'] = $this->safeDelete($db, Encounter::tableName(), ['id' => $encounterIds], $errors);
            }

            // 3) Turnos e internaciones anclados a la persona
            $counts['turnos'] = $this->safeDelete($db, Turno::tableName(), ['id_persona' => $personaIds], $errors);

            $internacionIds = (new Query())
                ->select('id')
                ->from(SegNivelInternacion::tableName())
                ->where(['id_persona' => $personaIds])
                ->column($db);
            $internacionIds = $this->normalizeIds($internacionIds);
            if ($internacionIds !== []) {
                $this->safeDelete($db, SegNivelInternacionHcama::tableName(), ['id_internacion' => $internacionIds], $errors);
                $counts['internaciones'] = $this->safeDelete(
                    $db,
                    SegNivelInternacion::tableName(),
                    ['id' => $internacionIds],
                    $errors
                );
            }

            // 4) Residuos clínicos por persona (SET NULL / sin cascade desde encounter)
            $this->hardDeletePersonaClinical($db, $personaIds, $errors);

            // 5) PES soft-deleted (staff demo)
            $pesIds = (new Query())
                ->select('id')
                ->from(ProfesionalEfectorServicio::tableName())
                ->where(['id_persona' => $personaIds])
                ->andWhere(['not', ['deleted_at' => null]])
                ->column($db);
            $pesIds = $this->normalizeIds($pesIds);
            if ($pesIds !== []) {
                $this->hardDeletePesChildren($db, $pesIds);
                $counts['pes'] = $this->safeDelete(
                    $db,
                    ProfesionalEfectorServicio::tableName(),
                    ['id' => $pesIds],
                    $errors
                );
            }

            $userIds = [];
            foreach ($personaIds as $idPersona) {
                $persona = Persona::findOne($idPersona);
                if ($persona === null) {
                    continue;
                }
                $idUser = (int) ($persona->id_user ?? 0);
                if ($idUser > 0) {
                    $userIds[] = $idUser;
                }
            }
            $userIdsFromEmail = (new Query())
                ->select('id')
                ->from(User::tableName())
                ->where(['or',
                    ['like', 'email', 'purged_%@demo.bioenlace.local', false],
                    ['and',
                        ['like', 'email', '%@demo.bioenlace.local', false],
                        ['or',
                            ['like', 'username', 'x\\_%', false],
                            ['like', 'username', 'x\\_p\\_%', false],
                        ],
                    ],
                ])
                ->column($db);
            $userIds = array_values(array_unique(array_merge(
                $userIds,
                array_map('intval', $userIdsFromEmail)
            )));

            $personasToDelete = [];
            foreach ($personaIds as $idPersona) {
                $stillHasGuardia = (new Query())
                    ->from(Guardia::tableName())
                    ->where(['id_persona' => $idPersona])
                    ->exists($db);
                $stillHasEncounter = (new Query())
                    ->from(Encounter::tableName())
                    ->where(['subject_persona_id' => $idPersona])
                    ->exists($db);
                $stillHasPes = (new Query())
                    ->from(ProfesionalEfectorServicio::tableName())
                    ->where(['id_persona' => $idPersona])
                    ->exists($db);
                $stillHasTurno = (new Query())
                    ->from(Turno::tableName())
                    ->where(['id_persona' => $idPersona])
                    ->exists($db);
                if (!$stillHasGuardia && !$stillHasEncounter && !$stillHasPes && !$stillHasTurno) {
                    $personasToDelete[] = $idPersona;
                }
            }

            if ($personasToDelete !== []) {
                $counts['personas'] = $this->safeDelete(
                    $db,
                    Persona::tableName(),
                    ['id_persona' => $personasToDelete],
                    $errors
                );
            }

            if ($userIds !== []) {
                $orphanUsers = [];
                foreach ($userIds as $uid) {
                    $linked = (new Query())
                        ->from(Persona::tableName())
                        ->where(['id_user' => $uid])
                        ->exists($db);
                    if (!$linked) {
                        $orphanUsers[] = $uid;
                    }
                }
                if ($orphanUsers !== []) {
                    $counts['users'] = $this->safeDelete($db, User::tableName(), ['id' => $orphanUsers], $errors);
                }
            }

            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            Yii::error('demo sandbox hard-delete: ' . $e->getMessage(), __METHOD__);

            return [
                'personas' => 0,
                'guardias' => 0,
                'encounters' => 0,
                'turnos' => 0,
                'internaciones' => 0,
                'pes' => 0,
                'users' => 0,
                'errors' => [$e->getMessage()],
            ];
        }

        $counts['errors'] = $errors;

        return $counts;
    }

    /**
     * @param list<int> $encounterIds
     * @param list<string> $errors
     */
    private function softDeleteEncounters(array $encounterIds, string $now, array &$errors): void
    {
        foreach ($encounterIds as $idEncounter) {
            try {
                \common\models\ConsultaChatMessage::deleteAll(['encounter_id' => $idEncounter]);
            } catch (\Throwable $e) {
                $errors[] = 'chat ' . $idEncounter . ': ' . $e->getMessage();
            }
            $encounter = Encounter::findOne($idEncounter);
            if ($encounter !== null && $encounter->deleted_at === null) {
                $encounter->deleted_at = $now;
                $encounter->save(false);
            }
        }
    }

    /**
     * @param list<int> $encounterIds
     * @param list<string> $errors
     */
    private function hardDeleteEncounterChildren(Connection $db, array $encounterIds, array &$errors): void
    {
        if ($encounterIds === []) {
            return;
        }

        foreach (self::ENCOUNTER_CHILD_TABLES as $table) {
            $schema = $db->schema->getTableSchema($table, true);
            if ($schema === null) {
                continue;
            }
            $condition = null;
            if (isset($schema->columns['encounter_id'])) {
                $condition = ['encounter_id' => $encounterIds];
            } elseif (isset($schema->columns['id_consulta'])) {
                $condition = ['id_consulta' => $encounterIds];
            }
            if ($condition === null) {
                continue;
            }
            $this->safeDelete($db, $table, $condition, $errors);
        }
    }

    /**
     * @param list<int> $personaIds
     * @param list<string> $errors
     */
    private function hardDeletePersonaClinical(Connection $db, array $personaIds, array &$errors): void
    {
        if ($personaIds === []) {
            return;
        }
        foreach (self::PERSONA_CLINICAL_TABLES as [$table, $col]) {
            $schema = $db->schema->getTableSchema($table, true);
            if ($schema === null || !isset($schema->columns[$col])) {
                continue;
            }
            $this->safeDelete($db, $table, [$col => $personaIds], $errors);
        }
    }

    /**
     * @param list<int> $pesIds
     */
    private function hardDeletePesChildren(Connection $db, array $pesIds): void
    {
        if ($pesIds === []) {
            return;
        }

        $childTables = [
            ProfesionalEfectorServicioAgendaVersion::tableName(),
            ProfesionalEfectorServicioAgenda::tableName(),
            '{{%profesional_efector_servicio_condicion_laboral}}',
            '{{%profesional_horario}}',
            '{{%profesional_horario_plantilla}}',
        ];
        foreach ($childTables as $table) {
            $schema = $db->schema->getTableSchema($table, true);
            if ($schema === null || !isset($schema->columns['id_profesional_efector_servicio'])) {
                continue;
            }
            $db->createCommand()
                ->delete($table, ['id_profesional_efector_servicio' => $pesIds])
                ->execute();
        }

        foreach ([
            [Guardia::tableName(), 'id_profesional_efector_servicio'],
            [Encounter::tableName(), 'id_profesional_efector_servicio'],
            [Turno::tableName(), 'id_profesional_efector_servicio'],
        ] as [$table, $col]) {
            $schema = $db->schema->getTableSchema($table, true);
            if ($schema === null || !isset($schema->columns[$col])) {
                continue;
            }
            $db->createCommand()
                ->update($table, [$col => null], [$col => $pesIds])
                ->execute();
        }
    }

    /**
     * @param array<string, mixed> $condition
     * @param list<string> $errors
     */
    private function safeDelete(Connection $db, string $table, array $condition, array &$errors): int
    {
        try {
            return (int) $db->createCommand()->delete($table, $condition)->execute();
        } catch (\Throwable $e) {
            $errors[] = $table . ': ' . $e->getMessage();

            return 0;
        }
    }

    /**
     * @param list<mixed> $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    private function retireDocumento(string $documento, int $id): string
    {
        $digits = preg_replace('/\D+/', '', $documento) ?? '';
        $base = substr($digits !== '' ? $digits : (string) $id, -6);

        return 'X' . str_pad($base, 6, '0', STR_PAD_LEFT) . substr((string) ($id % 10), -1);
    }
}
