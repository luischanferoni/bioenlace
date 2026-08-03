<?php

namespace common\components\Domain\Organization\Service\Seed;

use common\components\Domain\Scheduling\Service\TurnoSlotClaimService;
use common\models\Clinical\Encounter;
use common\models\Guardia;
use common\models\InfraestructuraCama;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraSala;
use common\models\Person\Persona;
use common\models\Platform\DemoSandboxSession;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\Scheduling\Turno;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionHcama;
use common\models\User;
use Yii;
use yii\db\Expression;

/**
 * Purga filas creadas por una sesión demo sandbox.
 */
final class DemoSandboxPurgeService
{
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

        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            foreach ((array) ($payload['turno_ids'] ?? []) as $idTurno) {
                $idTurno = (int) $idTurno;
                if ($idTurno <= 0) {
                    continue;
                }
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

            foreach ((array) ($payload['encounter_ids'] ?? []) as $idEncounter) {
                $idEncounter = (int) $idEncounter;
                if ($idEncounter <= 0) {
                    continue;
                }
                $encounter = Encounter::findOne($idEncounter);
                if ($encounter !== null && $encounter->deleted_at === null) {
                    $encounter->deleted_at = $now;
                    $encounter->save(false);
                }
            }

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

            foreach ((array) ($payload['guardia_ids'] ?? []) as $idGuardia) {
                $idGuardia = (int) $idGuardia;
                if ($idGuardia <= 0) {
                    continue;
                }
                $g = Guardia::findOne($idGuardia);
                if ($g !== null && $g->hasAttribute('deleted_at')) {
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

            foreach ((array) ($payload['paciente_ids'] ?? []) as $idPaciente) {
                $idPaciente = (int) $idPaciente;
                if ($idPaciente <= 0) {
                    continue;
                }
                $persona = Persona::findOne($idPaciente);
                if ($persona !== null && (int) ($persona->id_user ?? 0) === 0) {
                    $persona->documento = $this->retireDocumento((string) $persona->documento, $idPaciente);
                    $persona->apellido = 'DemoPurged';
                    $persona->save(false);
                }
            }

            $staff = Persona::findOne((int) $session->id_persona);
            if ($staff !== null) {
                $staff->documento = $this->retireDocumento((string) $staff->documento, (int) $staff->id_persona);
                $staff->apellido = 'DemoPurged';
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

    private function retireDocumento(string $documento, int $id): string
    {
        $digits = preg_replace('/\D+/', '', $documento) ?? '';
        $base = substr($digits !== '' ? $digits : (string) $id, -6);

        return 'X' . str_pad($base, 6, '0', STR_PAD_LEFT) . substr((string) ($id % 10), -1);
    }
}
