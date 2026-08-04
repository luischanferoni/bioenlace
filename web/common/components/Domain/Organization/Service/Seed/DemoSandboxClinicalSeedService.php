<?php

namespace common\components\Domain\Organization\Service\Seed;

use common\components\Domain\Clinical\Emergency\Service\GuardiaCircuitoService;
use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Clinical\Service\CarePlanLifecycleService;
use common\components\Domain\Clinical\Service\EncounterLifecycleService;
use common\components\Domain\Person\Util\CuilValidator;
use common\components\Domain\Scheduling\Service\ConsultaAsyncInitialChatService;
use common\components\Domain\Scheduling\Service\TurnoSlotClaimService;
use common\models\Clinical\Encounter;
use common\models\Guardia;
use common\models\InfraestructuraCama;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraSala;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionRepository;
use common\models\SegNivelInternacionTipoIngreso;
use common\models\User;
use Yii;

/**
 * Seed clínico mínimo para una sesión demo (pacientes, turnos, consulta AMB, async VR, guardia, internación).
 */
final class DemoSandboxClinicalSeedService
{
    public const SEED_MARKER = 'seed:demo-sandbox-clinical';

    /** Mensajes paciente para bandeja Virtual (Por tomar). */
    private const ASYNC_MENSAJES = [
        'Tengo dolor de garganta desde hace dos días y algo de fiebre. ¿Debo consultar presencial o alcanza con medicación?',
        'Quería consultar por renovación de medicación para la presión. Sigo estable y sin efectos adversos.',
    ];

    /**
     * @param array{
     *     pacientes?: int,
     *     turnos?: int,
     *     with_consulta_amb?: bool,
     *     with_consulta_async?: bool,
     *     consultas_async?: int,
     *     with_guardia?: bool,
     *     with_internacion?: bool
     * } $options
     * @return array{
     *     paciente_ids: list<int>,
     *     turno_ids: list<int>,
     *     encounter_ids: list<int>,
     *     async_encounter_ids: list<int>,
     *     guardia_ids: list<int>,
     *     internacion_ids: list<int>,
     *     cama_ids: list<int>,
     *     sala_ids: list<int>,
     *     piso_ids: list<int>,
     *     documentos_pacientes: list<string>,
     *     fecha_turnos: string
     * }
     */
    public function seedForStaff(
        int $idEfector,
        int $idPes,
        int $idServicio,
        int $actingUserId,
        array $options = []
    ): array {
        $withGuardia = (bool) ($options['with_guardia'] ?? false);
        $withInternacion = (bool) ($options['with_internacion'] ?? false);
        $withConsultaAmb = (bool) ($options['with_consulta_amb'] ?? true);
        $withConsultaAsync = (bool) ($options['with_consulta_async'] ?? true);
        $nAsync = $withConsultaAsync
            ? max(0, (int) ($options['consultas_async'] ?? 2))
            : 0;
        $nTurnos = max(0, (int) ($options['turnos'] ?? 2));
        if ($withConsultaAmb && $nTurnos < 1) {
            $nTurnos = 1;
        }
        $minPacientes = $nTurnos + $nAsync + ($withGuardia ? 1 : 0) + ($withInternacion ? 1 : 0);
        $nPacientes = max($minPacientes, max(1, (int) ($options['pacientes'] ?? 4)));

        $pacienteIds = [];
        $documentos = [];
        for ($i = 0; $i < $nPacientes; $i++) {
            // Pacientes de async llevan usuario: el chat inicial exige id_user.
            $withUser = $i >= $nTurnos && $i < ($nTurnos + $nAsync);
            [$idPersona, $doc] = $this->createPaciente($i + 1, $withUser);
            $pacienteIds[] = $idPersona;
            $documentos[] = $doc;
        }

        $turnoIds = [];
        $fecha = $this->nextWeekdayDate();
        $horas = $this->slotHours($nTurnos);
        $limit = min($nTurnos, count($pacienteIds), count($horas));
        for ($i = 0; $i < $limit; $i++) {
            try {
                $idTurno = $this->createTurno(
                    $pacienteIds[$i],
                    $idEfector,
                    $idPes,
                    $idServicio,
                    $fecha,
                    $horas[$i],
                    $actingUserId
                );
                if ($idTurno > 0) {
                    $turnoIds[] = $idTurno;
                }
            } catch (\Throwable $e) {
                Yii::error('demo sandbox turno seed: ' . $e->getMessage(), __METHOD__);
            }
        }
        if ($nTurnos > 0 && $turnoIds === []) {
            throw new \RuntimeException(
                'Seed demo: no se pudo crear ningún turno (fecha=' . $fecha . ', pes=' . $idPes . ').'
            );
        }

        $encounterIds = [];
        if ($withConsultaAmb && $turnoIds !== []) {
            $encounterIds = $this->ensureConsultaAmbFromFirstTurno($turnoIds[0], $actingUserId);
        }

        $asyncEncounterIds = [];
        $asyncStart = $limit;
        for ($i = 0; $i < $nAsync; $i++) {
            $idx = $asyncStart + $i;
            if (!isset($pacienteIds[$idx])) {
                break;
            }
            try {
                $mensaje = self::ASYNC_MENSAJES[$i % count(self::ASYNC_MENSAJES)];
                $idEnc = $this->createConsultaAsync(
                    $pacienteIds[$idx],
                    $idEfector,
                    $idServicio,
                    $mensaje,
                    $actingUserId
                );
                if ($idEnc > 0) {
                    $asyncEncounterIds[] = $idEnc;
                    $encounterIds[] = $idEnc;
                }
            } catch (\Throwable $e) {
                Yii::warning('demo sandbox consulta async VR seed: ' . $e->getMessage(), __METHOD__);
            }
        }

        $nextIdx = $asyncStart + $nAsync;
        $guardiaIds = [];
        if ($withGuardia && isset($pacienteIds[$nextIdx])) {
            try {
                $gid = $this->createGuardia(
                    $pacienteIds[$nextIdx],
                    $idEfector,
                    $idPes,
                    $actingUserId
                );
                if ($gid > 0) {
                    $guardiaIds[] = $gid;
                }
            } catch (\Throwable $e) {
                Yii::warning('demo sandbox guardia seed: ' . $e->getMessage(), __METHOD__);
            }
            $nextIdx++;
        }

        $internacionIds = [];
        $camaIds = [];
        $salaIds = [];
        $pisoIds = [];
        if ($withInternacion && isset($pacienteIds[$nextIdx])) {
            try {
                $infra = $this->createBedInfra($idEfector, $idServicio);
                $pisoIds[] = $infra['id_piso'];
                $salaIds[] = $infra['id_sala'];
                $camaIds[] = $infra['id_cama'];

                $idInternacion = $this->createInternacion(
                    $pacienteIds[$nextIdx],
                    $infra['id_cama'],
                    $idPes,
                    $actingUserId
                );
                if ($idInternacion > 0) {
                    $internacionIds[] = $idInternacion;
                }
            } catch (\Throwable $e) {
                Yii::warning('demo sandbox internacion seed: ' . $e->getMessage(), __METHOD__);
            }
        }

        Yii::info(
            'demo sandbox seed ok efector=' . $idEfector
            . ' pes=' . $idPes
            . ' pacientes=' . count($pacienteIds)
            . ' turnos=' . count($turnoIds)
            . ' encounters=' . count($encounterIds)
            . ' async_vr=' . count($asyncEncounterIds)
            . ' guardia=' . count($guardiaIds)
            . ' internacion=' . count($internacionIds)
            . ' fecha_turnos=' . $fecha,
            __METHOD__
        );

        return [
            'paciente_ids' => $pacienteIds,
            'turno_ids' => $turnoIds,
            'encounter_ids' => $encounterIds,
            'async_encounter_ids' => $asyncEncounterIds,
            'guardia_ids' => $guardiaIds,
            'internacion_ids' => $internacionIds,
            'cama_ids' => $camaIds,
            'sala_ids' => $salaIds,
            'piso_ids' => $pisoIds,
            'documentos_pacientes' => $documentos,
            'fecha_turnos' => $fecha,
        ];
    }

    /**
     * Solicitud async planificada (bandeja Virtual → Por tomar), sin PES asignado.
     */
    private function createConsultaAsync(
        int $idPersona,
        int $idEfector,
        int $idServicio,
        string $mensaje,
        int $actingUserId
    ): int {
        $meta = [
            'tipo' => 'consulta_async_solicitud',
            'seed' => self::SEED_MARKER,
            'urgency_band' => 'C',
            'reserva_triage_code' => 'demo_sandbox',
        ];

        // Sin PES: queda en «Por tomar». id vacío evita que start() tome el PES de sesión Yii.
        $encounter = (new EncounterLifecycleService())->start([
            'subject_persona_id' => $idPersona,
            'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
            'service_id' => $idServicio,
            'efector_id' => $idEfector,
            'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
            'parent_id' => null,
            'reason_text' => $mensaje,
            'note' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'id_profesional_efector_servicio' => '',
        ]);
        $encounter->status = EncounterStatus::PLANNED;
        $encounter->id_profesional_efector_servicio = null;
        ActiveRecordConsoleBlame::prepareForSave($encounter, $actingUserId);
        if (!$encounter->save(false)) {
            throw new \RuntimeException('No se pudo guardar encounter async demo.');
        }

        try {
            (new ConsultaAsyncInitialChatService())->seedMensajePaciente(
                $encounter,
                $idPersona,
                $mensaje,
                $meta
            );
        } catch (\Throwable $e) {
            Yii::warning('demo sandbox chat async: ' . $e->getMessage(), __METHOD__);
        }

        return (int) $encounter->id;
    }

    /**
     * Encounter AMB in-progress ligado al primer turno (captura clínica).
     *
     * @return list<int>
     */
    private function ensureConsultaAmbFromFirstTurno(int $idTurno, int $actingUserId): array
    {
        $turno = Turno::findOne($idTurno);
        if ($turno === null) {
            return [];
        }

        try {
            $turno->estado = Turno::ESTADO_EN_ATENCION;
            $turno->atendido = 'EN ATENCION';
            ActiveRecordConsoleBlame::prepareForSave($turno, $actingUserId);
            $turno->save(false);

            $encounter = (new EncounterLifecycleService())->ensureFromTurno($turno);
            if ($encounter !== null) {
                return [(int) $encounter->id];
            }
        } catch (\Throwable $e) {
            Yii::warning('demo sandbox consulta AMB seed: ' . $e->getMessage(), __METHOD__);
        }

        $existing = Encounter::find()
            ->where(['appointment_id' => $idTurno, 'deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $existing !== null ? [(int) $existing->id] : [];
    }

    /**
     * Ingreso a guardia sin depender de sesión HTTP (provision pre-login).
     */
    private function createGuardia(int $idPersona, int $idEfector, int $idPes, int $actingUserId): int
    {
        $model = new Guardia();
        $model->scenario = Guardia::INGRESO_PACIENTE;
        $model->id_persona = $idPersona;
        $model->id_efector = $idEfector;
        $model->id_profesional_efector_servicio = $idPes;
        $model->ingresa_en = 'deambula';
        $model->ingresa_con = 'solo';
        $model->datos_contacto_tel = '1111111111';
        $model->situacion_al_ingresar = 'Ingreso demo sandbox (datos de prueba).';
        $model->fecha = date('d/m/Y');
        $model->hora = date('H:i');

        ActiveRecordConsoleBlame::prepareForSave($model, $actingUserId);

        if (!$model->validate()) {
            throw new \InvalidArgumentException(
                'Guardia demo inválida: ' . json_encode($model->getFirstErrors(), JSON_UNESCAPED_UNICODE)
            );
        }
        if (!$model->save(false)) {
            throw new \RuntimeException('No se pudo registrar guardia demo.');
        }

        (new GuardiaCircuitoService())->afterIngreso($model);

        return (int) $model->id;
    }

    /**
     * Infra de cama efímera por sesión (no reutiliza camas del efector).
     *
     * @return array{id_piso: int, id_sala: int, id_cama: int}
     */
    private function createBedInfra(int $idEfector, int $idServicio): array
    {
        $piso = new InfraestructuraPiso();
        $piso->nro_piso = 99;
        $piso->descripcion = 'Demo sandbox';
        $piso->id_efector = $idEfector;
        if (!$piso->save(false)) {
            throw new \RuntimeException('Piso demo: ' . json_encode($piso->getErrors()));
        }

        $sala = new InfraestructuraSala();
        $sala->nro_sala = 1;
        $sala->descripcion = 'Sala demo sandbox';
        $sala->id_piso = (int) $piso->id;
        $sala->id_servicio = $idServicio > 0 ? $idServicio : null;
        $sala->tipo_sala = 'indistinto';
        if (!$sala->save(false)) {
            throw new \RuntimeException('Sala demo: ' . json_encode($sala->getErrors()));
        }

        $cama = new InfraestructuraCama();
        $cama->nro_cama = 1;
        $cama->id_sala = (int) $sala->id;
        $cama->estado = 'desocupada';
        $cama->respirador = 0;
        $cama->monitor = 0;
        if (!$cama->save(false)) {
            throw new \RuntimeException('Cama demo: ' . json_encode($cama->getErrors()));
        }

        return [
            'id_piso' => (int) $piso->id,
            'id_sala' => (int) $sala->id,
            'id_cama' => (int) $cama->id,
        ];
    }

    /**
     * Ingreso sin assert de permiso HTTP (provision pre-login).
     */
    private function createInternacion(
        int $idPersona,
        int $idCama,
        int $idPes,
        int $actingUserId
    ): int {
        if (SegNivelInternacion::personaInternada($idPersona)) {
            throw new \InvalidArgumentException('Paciente ya internado.');
        }

        $cama = InfraestructuraCama::findOne($idCama);
        if ($cama === null) {
            throw new \InvalidArgumentException('Cama demo inexistente.');
        }

        $model = new SegNivelInternacion();
        $model->scenario = SegNivelInternacion::INGRESO_PACIENTE;
        $model->id_persona = $idPersona;
        $model->id_cama = $idCama;
        $model->id_profesional_efector_servicio = $idPes;
        $model->id_tipo_ingreso = $this->resolveIdTipoIngreso();
        $model->ingresa_en = 'deambula';
        $model->ingresa_con = 'solo';
        $model->datos_contacto_tel = '';
        $model->situacion_al_ingresar = 'Internación demo sandbox (datos de prueba).';
        $model->fecha_inicio = date('d/m/Y');
        $model->hora_inicio = date('H:i');

        ActiveRecordConsoleBlame::prepareForSave($model, $actingUserId);

        $cama->estado = 'ocupada';

        if (!$model->validate()) {
            throw new \InvalidArgumentException(
                'Internación demo inválida: ' . json_encode($model->getFirstErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        if (!$model->save(false)) {
            throw new \RuntimeException('No se pudo guardar internación demo.');
        }
        if (!$cama->save(false)) {
            throw new \RuntimeException('No se pudo ocupar cama demo.');
        }

        SegNivelInternacionRepository::doAgregarHistoriaCama($model);

        try {
            (new CarePlanLifecycleService())->onInternacionAdmission($model);
        } catch (\Throwable $e) {
            Yii::warning('demo sandbox care plan internacion: ' . $e->getMessage(), __METHOD__);
        }

        return (int) $model->id;
    }

    /**
     * id válido en seg_nivel_internacion_tipo_ingreso (no hardcodear: en prod el catálogo puede diferir).
     */
    private function resolveIdTipoIngreso(): int
    {
        $preferLabels = ['Consultorio', 'Programada', 'Programado', 'Guardia'];
        foreach ($preferLabels as $label) {
            $row = SegNivelInternacionTipoIngreso::find()
                ->where(['tipo_ingreso' => $label])
                ->orderBy(['id' => SORT_ASC])
                ->one();
            if ($row !== null) {
                return (int) $row->id;
            }
        }

        $any = SegNivelInternacionTipoIngreso::find()->orderBy(['id' => SORT_ASC])->one();
        if ($any !== null) {
            return (int) $any->id;
        }

        // Catálogo vacío: una fila mínima para pasar el exist validator.
        $row = new SegNivelInternacionTipoIngreso();
        $row->tipo_ingreso = 'Consultorio';
        if (!$row->save(false)) {
            throw new \RuntimeException(
                'No se pudo crear tipo_ingreso demo: ' . json_encode($row->getErrors())
            );
        }

        return (int) $row->id;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function createPaciente(int $ordinal, bool $withUser = false): array
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $documento = '37' . $suffix;
            if (Persona::find()->where(['documento' => $documento])->exists()) {
                continue;
            }

            // apellido/nombre: solo letras (regla Persona); el ordinal va en el documento 37xxxxxx.
            $apellidos = ['Alonso', 'Benitez', 'Castro', 'Dominguez', 'Espinoza', 'Fernandez', 'Gomez', 'Herrera'];
            $persona = new Persona();
            $persona->scenario = Persona::SCENARIOCREATEUPDATE;
            $persona->nombre = 'Paciente';
            $persona->apellido = $apellidos[($ordinal - 1) % count($apellidos)];
            $persona->documento = $documento;
            $persona->fecha_nacimiento = sprintf('1990-%02d-15', min(12, max(1, $ordinal)));
            $persona->id_tipodoc = 1;
            $persona->id_estado_civil = 1;
            $persona->acredita_identidad = 1;
            $persona->sexo_biologico = 1;
            $persona->genero = 1;

            if (!$persona->save()) {
                throw new \RuntimeException('Paciente demo: ' . json_encode($persona->getErrors()));
            }
            $persona->cuil = CuilValidator::buildFromDni($documento);
            $persona->save(false, ['cuil']);

            if ($withUser) {
                $username = 'demo_p_' . $suffix;
                $user = new User();
                $user->username = $username;
                $user->email = $username . '@demo.bioenlace.local';
                $user->status = User::STATUS_ACTIVE;
                $user->setPassword(bin2hex(random_bytes(12)));
                $user->generateAuthKey();
                if (!$user->save(false)) {
                    throw new \RuntimeException('User paciente demo: ' . json_encode($user->getErrors()));
                }
                $persona->id_user = (int) $user->id;
                $persona->save(false, ['id_user']);
            }

            return [(int) $persona->id_persona, $documento];
        }

        throw new \RuntimeException('No se pudo asignar documento único para paciente demo.');
    }

    private function createTurno(
        int $idPersona,
        int $idEfector,
        int $idPes,
        int $idServicio,
        string $fecha,
        string $hora,
        int $actingUserId
    ): int {
        $horaNorm = strlen($hora) === 5 ? $hora . ':00' : $hora;
        $horaFinTs = strtotime($fecha . ' ' . $horaNorm);
        $horaFin = $horaFinTs !== false ? date('H:i:s', $horaFinTs + 15 * 60) : null;

        $turno = new Turno();
        $turno->id_persona = $idPersona;
        $turno->id_efector = $idEfector;
        $turno->id_profesional_efector_servicio = $idPes;
        $turno->id_servicio = $idServicio;
        $turno->id_servicio_asignado = $idServicio;
        $turno->fecha = $fecha;
        $turno->hora = $horaNorm;
        if ($horaFin !== null) {
            $turno->hora_fin = $horaFin;
        }
        $turno->intervalo_minutos_reserva = 15;
        $turno->estado = Turno::ESTADO_PENDIENTE;
        $turno->confirmado = null;
        $turno->referenciado = null;
        $turno->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;
        $turno->usuario_alta = 'demo-sandbox';
        $turno->fecha_alta = $fecha;
        $turno->usuario_mod = 'demo-sandbox';
        $turno->fecha_mod = $fecha;
        $turno->appointment_source_system = 'demo-sandbox';
        $turno->external_appointment_id = 'demo-' . $idPes . '-' . $fecha . '-' . str_replace(':', '', substr($horaNorm, 0, 5));

        ActiveRecordConsoleBlame::prepareForSave($turno, $actingUserId);
        try {
            if (!$turno->save(false)) {
                throw new \RuntimeException('save(false) rechazado: ' . json_encode($turno->getErrors()));
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Turno demo: ' . $e->getMessage(), 0, $e);
        }

        $idTurno = (int) $turno->id_turnos;
        if ($idTurno <= 0) {
            throw new \RuntimeException('Turno demo sin id_turnos tras save.');
        }
        TurnoSlotClaimService::tryClaim($idPes, $fecha, substr($horaNorm, 0, 5), $idTurno);

        return $idTurno;
    }

    /**
     * @return list<string> HH:MM
     */
    private function slotHours(int $count): array
    {
        $count = max(1, $count);
        $out = [];
        $base = strtotime(date('Y-m-d') . ' 10:00:00') ?: time();
        for ($i = 0; $i < $count; $i++) {
            $out[] = date('H:i', $base + ($i * 1800));
        }

        return $out;
    }

    private function nextWeekdayDate(): string
    {
        $ts = time();
        for ($i = 0; $i < 7; $i++) {
            $dow = (int) date('N', $ts);
            if ($dow >= 1 && $dow <= 5) {
                return date('Y-m-d', $ts);
            }
            $ts = strtotime('+1 day', $ts) ?: ($ts + 86400);
        }

        return date('Y-m-d');
    }
}
