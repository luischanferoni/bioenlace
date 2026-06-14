<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use Yii;
use yii\db\Query;

/**
 * Export CSV de indicadores de guardia (día actual + histórico materializado).
 */
final class GuardiaIndicadoresExportService
{
    /**
     * @return array{content: string, filename: string}
     */
    public function buildCsv(int $idEfector, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $desde = $fechaDesde ?: date('Y-m-d', strtotime('-30 days'));
        $hasta = $fechaHasta ?: date('Y-m-d');

        $rows = [];
        $rows[] = [
            'fecha',
            'ingresos',
            'activos',
            'sin_triage',
            'minutos_mediana_triage',
            'minutos_mediana_medico',
            'sla_incumplidos_tablero',
        ];

        $table = '{{%guardia_metrics_daily}}';
        $hasMetrics = Yii::$app->db->schema->getTableSchema($table, true) !== null;

        if ($hasMetrics) {
            $historical = (new Query())
                ->from($table)
                ->where(['id_efector' => $idEfector])
                ->andWhere(['between', 'fecha', $desde, $hasta])
                ->orderBy(['fecha' => SORT_ASC])
                ->all();

            foreach ($historical as $row) {
                $payload = [];
                if (!empty($row['payload_json'])) {
                    $decoded = json_decode((string) $row['payload_json'], true);
                    if (is_array($decoded)) {
                        $payload = $decoded;
                    }
                }
                $rows[] = [
                    (string) $row['fecha'],
                    (string) ($row['ingresos'] ?? ''),
                    (string) ($payload['activos'] ?? ''),
                    (string) ($row['sin_triage'] ?? ''),
                    (string) ($row['minutos_mediana_triage'] ?? ''),
                    (string) ($row['minutos_mediana_medico'] ?? ''),
                    '',
                ];
            }
        }

        if ($hasta >= date('Y-m-d')) {
            $live = (new GuardiaIndicadoresService())->resumen($idEfector);
            $slaCount = (new GuardiaIndicadoresService())->contarSlaIncumplidosTablero($idEfector);
            $rows[] = [
                date('Y-m-d'),
                (string) ($live['ingresos_hoy'] ?? 0),
                (string) ($live['activos'] ?? 0),
                (string) ($live['sin_triage'] ?? 0),
                (string) ($live['tiempos_hoy']['minutos_a_triage'] ?? ''),
                (string) ($live['tiempos_hoy']['minutos_a_medico'] ?? ''),
                (string) $slaCount,
            ];
        }

        $fp = fopen('php://temp', 'r+');
        foreach ($rows as $line) {
            fputcsv($fp, $line, ';');
        }
        rewind($fp);
        $content = stream_get_contents($fp) ?: '';
        fclose($fp);

        $filename = sprintf('guardia-indicadores-efector-%d-%s.csv', $idEfector, date('Ymd'));

        return ['content' => $content, 'filename' => $filename];
    }
}
