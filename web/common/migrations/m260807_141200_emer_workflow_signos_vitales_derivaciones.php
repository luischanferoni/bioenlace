<?php

use yii\db\Migration;

/**
 * EMER workflow: signos vitales + derivaciones en la captura (no en tablero médico).
 */
class m260807_141200_emer_workflow_signos_vitales_derivaciones extends Migration
{
    public function safeUp()
    {
        $rows = (new \yii\db\Query())
            ->from('{{%encounter_definition}}')
            ->select(['id', 'workflow_json', 'encounter_class'])
            ->all();

        foreach ($rows as $row) {
            $class = strtoupper(trim((string) ($row['encounter_class'] ?? '')));
            if ($class !== 'EMER') {
                continue;
            }
            $json = (string) ($row['workflow_json'] ?? '');
            $decoded = json_decode($json, true);
            if (!is_array($decoded) || !isset($decoded['conf']) || !is_array($decoded['conf'])) {
                continue;
            }
            $conf = $decoded['conf'];
            $relacions = [];
            foreach ($conf as $step) {
                if (is_array($step) && isset($step['relacion'])) {
                    $relacions[(string) $step['relacion']] = true;
                }
            }
            $changed = false;
            if (!isset($relacions['ConsultaAtencionesEnfermeria'])) {
                array_splice($conf, 1, 0, [[
                    'titulo' => 'Signos vitales',
                    'relacion' => 'ConsultaAtencionesEnfermeria',
                    'requerido' => false,
                    'url' => '',
                ]]);
                $changed = true;
            }
            if (!isset($relacions['ConsultaDerivaciones'])) {
                $conf[] = [
                    'titulo' => 'Derivaciones',
                    'relacion' => 'ConsultaDerivaciones',
                    'requerido' => false,
                    'url' => '',
                ];
                $changed = true;
            }
            if (!$changed) {
                continue;
            }
            $decoded['conf'] = $conf;
            $this->update(
                '{{%encounter_definition}}',
                ['workflow_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['id' => (int) $row['id']]
            );
        }
    }

    public function safeDown()
    {
        echo "m260807_141200_emer_workflow_signos_vitales_derivaciones cannot be reverted.\n";

        return false;
    }
}
