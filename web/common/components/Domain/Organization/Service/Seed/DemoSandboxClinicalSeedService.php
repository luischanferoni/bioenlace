<?php

namespace common\components\Domain\Organization\Service\Seed;

use common\components\Domain\Clinical\Emergency\Service\GuardiaIngresoService;
use common\components\Domain\Clinical\Service\CarePlanLifecycleService;
use common\components\Domain\Clinical\Service\EncounterLifecycleService;
use common\components\Domain\Person\Util\CuilValidator;
use common\components\Domain\Scheduling\Service\TurnoSlotClaimService;
use common\models\Clinical\Encounter;
use common\models\InfraestructuraCama;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraSala;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionRepository;
use Yii;

/**
 * Seed clínico mínimo para una sesión demo (pacientes, turnos, consulta AMB, guardia, internación).
 */
final class DemoSandboxClinicalSeedService
{
    public const SEED_MARKER = 'seed:demo-sandbox-clinical';

    /**
     * @param array{
     *     pacientes?: int,
     *     turnos?: int,
     *     with_consulta_amb?: bool,
     *     with_guardia?: bool,
     *     with_internacion?: bool
     * } $options
     * @return array{
     *     paciente_ids: list<int>,
     *     turno_ids: list<int>,
     *     encounter_ids: list<int>,
     *     guardia_ids: list<int>,
     *     internacion_ids: list<int>,
     *     cama_ids: list<int>,
     *     sala_ids: list<int>,
     *     piso_ids: list<int>,
     *     documentos_pacientes: list<string>
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
        $nTurnos = max(0, (int) ($options['turnos'] ?? 2));
        if ($withConsultaAmb && $nTurnos < 1) {
            $nTurnos = 1;
        }
        $minPacientes = $nTurnos + ($withGuardia ? 1 : 0) + ($withInternacion ? 1 : 0);
        $nPacientes = max($minPacientes, max(1, (int) ($options['pacientes'] ?? 4)));

        $pacienteIds = [];
        $documentos = [];
        for ($i = 0; $i < $nPacientes; $i++) {
            [$idPersona, $doc] = $this->createPaciente($i + 1);
            $pacienteIds[] = $idPersona;
            $documentos[] = $doc;
        }

        $turnoIds = [];
        $fecha = $this->nextWeekdayDate();
        $horas = ['09:00', '09:30', '10:00', '10:30', '11:00'];
        $limit = min($nTurnos, count($pacienteIds), count($horas));
        for ($i = 0; $i < $limit; $i++) {
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
        }

        $encounterIds = [];
        if ($withConsultaAmb && $turnoIds !== []) {
            $encounterIds = $this->ensureConsultaAmbFromFirstTurno($turnoIds[0]);
        }

        $nextIdx = $limit;
        $guardiaIds = [];
        if ($withGuardia && isset($pacienteIds[$nextIdx])) {
            try {
                $result = (new GuardiaIngresoService())->ingresar([
                    'id_persona' => $pacienteIds[$nextIdx],
                    'id_profesional_efector_servicio' => $idPes,
                    'ingresa_en' => 'deambula',
                    'ingresa_con' => 'solo',
                    'datos_contacto_tel' => '1111111111',
                    'situacion_al_ingresar' => 'Ingreso demo sandbox (datos de prueba).',
                ], $idEfector);
                $gid = (int) ($result['id'] ?? 0);
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

        return [
            'paciente_ids' => $pacienteIds,
            'turno_ids' => $turnoIds,
            'encounter_ids' => $encounterIds,
            'guardia_ids' => $guardiaIds,
            'internacion_ids' => $internacionIds,
            'cama_ids' => $camaIds,
            'sala_ids' => $salaIds,
            'piso_ids' => $pisoIds,
            'documentos_pacientes' => $documentos,
        ];
    }

    /**
     * Encounter AMB in-progress ligado al primer turno (captura clínica).
     *
     * @return list<int>
     */
    private function ensureConsultaAmbFromFirstTurno(int $idTurno): array
    {
        $turno = Turno::findOne($idTurno);
        if ($turno === null) {
            return [];
        }

        try {
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
        $model->id_tipo_ingreso = 2; // Consultorio
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
     * @return array{0: int, 1: string}
     */
    private function createPaciente(int $ordinal): array
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
        $turno = new Turno();
        $turno->id_persona = $idPersona;
        $turno->id_efector = $idEfector;
        $turno->id_profesional_efector_servicio = $idPes;
        $turno->id_servicio = $idServicio;
        $turno->id_servicio_asignado = $idServicio;
        $turno->fecha = $fecha;
        $turno->hora = $hora;
        $turno->estado = Turno::ESTADO_PENDIENTE;
        $turno->confirmado = 'NO';
        $turno->referenciado = 'NO';
        $turno->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;
        $turno->usuario_alta = 'demo-sandbox';
        $turno->fecha_alta = date('Y-m-d H:i:s');
        $turno->usuario_mod = 'demo-sandbox';
        $turno->fecha_mod = date('Y-m-d H:i:s');
        $turno->appointment_source_system = 'demo-sandbox';
        $turno->external_appointment_id = 'demo-' . $idPes . '-' . $fecha . '-' . str_replace(':', '', $hora);

        ActiveRecordConsoleBlame::prepareForSave($turno, $actingUserId);
        if (!$turno->save(false)) {
            Yii::warning('demo sandbox turno: ' . json_encode($turno->getErrors()), __METHOD__);

            return 0;
        }

        $idTurno = (int) $turno->id_turnos;
        TurnoSlotClaimService::tryClaim($idPes, $fecha, $hora, $idTurno);

        return $idTurno;
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
