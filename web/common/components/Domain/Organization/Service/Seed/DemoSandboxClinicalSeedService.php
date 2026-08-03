<?php

namespace common\components\Domain\Organization\Service\Seed;

use common\components\Domain\Clinical\Emergency\Service\GuardiaIngresoService;
use common\components\Domain\Person\Util\CuilValidator;
use common\components\Domain\Scheduling\Service\TurnoSlotClaimService;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use Yii;

/**
 * Seed clínico mínimo para una sesión demo (pacientes + turnos del día; guardia opcional).
 */
final class DemoSandboxClinicalSeedService
{
    public const SEED_MARKER = 'seed:demo-sandbox-clinical';

    /**
     * @param array{
     *     pacientes?: int,
     *     turnos?: int,
     *     with_guardia?: bool
     * } $options
     * @return array{
     *     paciente_ids: list<int>,
     *     turno_ids: list<int>,
     *     guardia_ids: list<int>,
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
        $nPacientes = max(1, (int) ($options['pacientes'] ?? 3));
        $nTurnos = max(0, (int) ($options['turnos'] ?? 3));
        $withGuardia = (bool) ($options['with_guardia'] ?? false);

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

        $guardiaIds = [];
        if ($withGuardia && $pacienteIds !== []) {
            try {
                $result = (new GuardiaIngresoService())->ingresar([
                    'id_persona' => $pacienteIds[0],
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
        }

        return [
            'paciente_ids' => $pacienteIds,
            'turno_ids' => $turnoIds,
            'guardia_ids' => $guardiaIds,
            'documentos_pacientes' => $documentos,
        ];
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

            $persona = new Persona();
            $persona->scenario = Persona::SCENARIOCREATEUPDATE;
            $persona->nombre = 'Paciente';
            $persona->apellido = 'Demo ' . $ordinal;
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
