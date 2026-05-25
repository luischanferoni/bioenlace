<?php

namespace common\components\Emergency;

use Yii;

/**
 * Persiste resumen diario en guardia_metrics_daily.
 */
final class GuardiaMetricsMaterializeService
{
    public function materializeForDate(int $idEfector, ?string $fechaYmd = null): void
    {
        $fecha = $fechaYmd ?: date('Y-m-d');
        $resumen = (new GuardiaIndicadoresService())->resumen($idEfector);
        $db = Yii::$app->db;
        $table = '{{%guardia_metrics_daily}}';
        if ($db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $payload = json_encode($resumen, JSON_UNESCAPED_UNICODE);
        $exists = (new \yii\db\Query())
            ->from($table)
            ->where(['id_efector' => $idEfector, 'fecha' => $fecha])
            ->exists($db);

        $row = [
            'ingresos' => (int) ($resumen['ingresos_hoy'] ?? 0),
            'sin_triage' => (int) ($resumen['sin_triage'] ?? 0),
            'minutos_mediana_triage' => $resumen['tiempos_hoy']['minutos_a_triage'] ?? null,
            'minutos_mediana_medico' => $resumen['tiempos_hoy']['minutos_a_medico'] ?? null,
            'payload_json' => $payload,
        ];

        if ($exists) {
            $db->createCommand()->update($table, $row, [
                'id_efector' => $idEfector,
                'fecha' => $fecha,
            ])->execute();
        } else {
            $db->createCommand()->insert($table, array_merge($row, [
                'id_efector' => $idEfector,
                'fecha' => $fecha,
                'created_at' => date('Y-m-d H:i:s'),
            ]))->execute();
        }
    }

    /**
     * Materializa todos los efectores con guardias en la fecha.
     */
    public function materializeAllEfectores(?string $fechaYmd = null): int
    {
        $fecha = $fechaYmd ?: date('Y-m-d');
        $ids = (new \yii\db\Query())
            ->select('id_efector')
            ->from('{{%guardia}}')
            ->where(['fecha' => $fecha])
            ->distinct()
            ->column();

        $n = 0;
        foreach ($ids as $idEfector) {
            $this->materializeForDate((int) $idEfector, $fecha);
            $n++;
        }

        return $n;
    }
}
