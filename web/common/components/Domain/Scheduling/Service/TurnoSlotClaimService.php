<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\TurnoResolucion;
use Yii;
use yii\db\Exception as DbException;

/**
 * Exclusión atómica de un slot (PES + fecha + hora) compartida entre reserva y adelantamiento.
 */
final class TurnoSlotClaimService
{
    /**
     * Intenta reclamar el slot para un turno. Si ya pertenece a ese turno, es éxito.
     */
    public static function tryClaim(int $idPes, string $fechaYmd, string $hora, int $idTurno): bool
    {
        $idPes = (int) $idPes;
        $idTurno = (int) $idTurno;
        $fechaYmd = trim($fechaYmd);
        $hora = TurnoResolucion::normalizarHora($hora);
        if ($idPes <= 0 || $idTurno <= 0 || $fechaYmd === '' || $hora === '') {
            return false;
        }

        $existing = (new \yii\db\Query())
            ->from('{{%turno_slot_claim}}')
            ->where([
                'id_profesional_efector_servicio' => $idPes,
                'fecha' => $fechaYmd,
                'hora' => $hora,
            ])
            ->one(Yii::$app->db);

        if (is_array($existing)) {
            return (int) ($existing['id_turno'] ?? 0) === $idTurno;
        }

        try {
            Yii::$app->db->createCommand()->insert('{{%turno_slot_claim}}', [
                'id_profesional_efector_servicio' => $idPes,
                'fecha' => $fechaYmd,
                'hora' => $hora,
                'id_turno' => $idTurno,
                'claimed_at' => date('Y-m-d H:i:s'),
            ])->execute();

            return true;
        } catch (DbException $e) {
            $again = (new \yii\db\Query())
                ->from('{{%turno_slot_claim}}')
                ->where([
                    'id_profesional_efector_servicio' => $idPes,
                    'fecha' => $fechaYmd,
                    'hora' => $hora,
                ])
                ->one(Yii::$app->db);

            return is_array($again) && (int) ($again['id_turno'] ?? 0) === $idTurno;
        }
    }

    public static function releaseForTurno(int $idTurno): void
    {
        if ($idTurno <= 0) {
            return;
        }
        Yii::$app->db->createCommand()
            ->delete('{{%turno_slot_claim}}', ['id_turno' => $idTurno])
            ->execute();
    }

    public static function moveClaim(int $idTurno, int $idPes, string $fechaYmd, string $hora): bool
    {
        self::releaseForTurno($idTurno);

        return self::tryClaim($idPes, $fechaYmd, $hora, $idTurno);
    }
}
