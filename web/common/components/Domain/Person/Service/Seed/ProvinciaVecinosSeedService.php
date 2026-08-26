<?php

namespace common\components\Domain\Person\Service\Seed;

use common\models\Pais;
use common\models\Provincia;
use Yii;

/**
 * Seed de vecinos por país (grafo en geo_provincia_vecinos).
 * Mapas por iso2 viven en el seed (no en el modelo Pais).
 */
final class ProvinciaVecinosSeedService
{
    /**
     * @return array{inserted: int, skipped: int}
     */
    public function upsertForIso2(string $iso2): array
    {
        $pais = Pais::requireByIso2($iso2);
        $map = $this->vecinosPorCodForIso2((string) $pais->iso2);
        $idPais = (int) $pais->id_pais;

        $byCod = [];
        foreach (Provincia::find()->where(['id_pais' => $idPais])->all() as $p) {
            $byCod[(string) $p->cod_indec] = (int) $p->id_provincia;
        }

        $inserted = 0;
        $skipped = 0;
        $db = Yii::$app->db;

        foreach ($map as $cod => $vecinos) {
            if (!isset($byCod[$cod])) {
                $skipped++;

                continue;
            }
            $idFrom = $byCod[$cod];
            foreach ($vecinos as $codVecino) {
                if (!isset($byCod[$codVecino])) {
                    $skipped++;

                    continue;
                }
                $idTo = $byCod[$codVecino];
                $exists = (new \yii\db\Query())
                    ->from('{{%geo_provincia_vecinos}}')
                    ->where(['id_provincia' => $idFrom, 'id_provincia_vecina' => $idTo])
                    ->exists($db);
                if ($exists) {
                    continue;
                }
                $db->createCommand()->insert('{{%geo_provincia_vecinos}}', [
                    'id_provincia' => $idFrom,
                    'id_provincia_vecina' => $idTo,
                ])->execute();
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * @return array<string, list<string>>
     */
    public function vecinosPorCodForIso2(string $iso2): array
    {
        return match (strtoupper(trim($iso2))) {
            'AR' => self::vecinosArgentinaPorCod(),
            'UY' => self::vecinosUruguayPorCod(),
            default => throw new \InvalidArgumentException('Sin mapa de vecinos para iso2=' . $iso2),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function vecinosArgentinaPorCod(): array
    {
        return [
            '02' => ['06', '14', '82'],
            '06' => ['02', '14', '82', '86'],
            '10' => ['14', '18', '22', '86'],
            '14' => ['06', '10', '18', '22', '86'],
            '18' => ['10', '14', '22', '86'],
            '22' => ['14', '18', '86', '34'],
            '26' => ['30', '42', '50', '62'],
            '30' => ['26', '42', '50'],
            '34' => ['22', '86', '90'],
            '38' => ['46', '66', '90'],
            '42' => ['26', '30', '50', '62', '74'],
            '46' => ['38', '66', '90'],
            '50' => ['26', '30', '42', '62', '70', '74'],
            '54' => ['58', '78', '86'],
            '58' => ['54', '78', '86'],
            '62' => ['26', '42', '50', '74'],
            '66' => ['38', '46', '90'],
            '70' => ['50', '74', '82'],
            '74' => ['42', '50', '62', '70', '82'],
            '78' => ['54', '58', '86'],
            '82' => ['06', '14', '18', '22', '86'],
            '86' => ['10', '14', '22', '66', '82', '90'],
            '90' => ['34', '38', '46', '66', '82', '86'],
            '94' => ['78', '26', '58'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function vecinosUruguayPorCod(): array
    {
        return [
            'MO' => ['CA', 'SJ'],
            'CA' => ['MO', 'SJ', 'FD', 'MA', 'LA'],
            'SJ' => ['MO', 'CA', 'FD', 'FS', 'CO'],
            'CO' => ['SJ', 'SO', 'FS'],
            'SO' => ['CO', 'RN', 'FS', 'DU'],
            'RN' => ['SO', 'PA', 'DU'],
            'PA' => ['RN', 'SA', 'TA', 'DU'],
            'SA' => ['PA', 'AR', 'TA'],
            'AR' => ['SA', 'RV', 'TA'],
            'RV' => ['AR', 'TA', 'TT'],
            'TA' => ['PA', 'SA', 'AR', 'RV', 'DU', 'TT'],
            'DU' => ['SO', 'RN', 'PA', 'TA', 'FS', 'FD', 'TT'],
            'FS' => ['SJ', 'CO', 'SO', 'DU', 'FD'],
            'FD' => ['CA', 'SJ', 'FS', 'DU', 'LA'],
            'LA' => ['CA', 'FD', 'MA', 'TT', 'RO'],
            'MA' => ['CA', 'LA', 'RO'],
            'RO' => ['MA', 'LA', 'TT', 'CL'],
            'TT' => ['RV', 'TA', 'DU', 'LA', 'RO', 'CL'],
            'CL' => ['RO', 'TT'],
        ];
    }
}
