<?php

use yii\db\Migration;

/**
 * Separa "Prácticas e indicaciones" en Prácticas realizadas + Indicaciones en encounter_definition.
 */
class m260714_143000_split_practicas_indicaciones_workflow extends Migration
{
    public function safeUp()
    {
        $rows = (new \yii\db\Query())
            ->from('{{%encounter_definition}}')
            ->select(['id', 'workflow_json'])
            ->all();

        foreach ($rows as $row) {
            $json = (string) ($row['workflow_json'] ?? '');
            if ($json === '') {
                continue;
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded) || !isset($decoded['conf']) || !is_array($decoded['conf'])) {
                continue;
            }

            $changed = false;
            $newConf = [];
            foreach ($decoded['conf'] as $step) {
                if (!is_array($step)) {
                    $newConf[] = $step;
                    continue;
                }
                $relacion = (string) ($step['relacion'] ?? '');
                $titulo = (string) ($step['titulo'] ?? '');
                $isCombined = $relacion === 'ConsultaPracticas'
                    && (
                        stripos($titulo, 'indicacion') !== false
                        || $titulo === 'Prácticas e indicaciones'
                    );

                if ($isCombined) {
                    $newConf[] = [
                        'titulo' => 'Prácticas realizadas',
                        'relacion' => 'ConsultaPracticas',
                        'requerido' => (bool) ($step['requerido'] ?? false),
                        'url' => (string) ($step['url'] ?? ''),
                    ];
                    $newConf[] = [
                        'titulo' => 'Indicaciones',
                        'relacion' => 'ConsultaIndicaciones',
                        'requerido' => false,
                        'url' => '',
                    ];
                    $changed = true;
                    continue;
                }

                // IMP: Indicaciones aún apuntaban a ConsultaPracticas
                if ($relacion === 'ConsultaPracticas' && $titulo === 'Indicaciones') {
                    $step['relacion'] = 'ConsultaIndicaciones';
                    $changed = true;
                }

                $newConf[] = $step;
            }

            if (!$changed) {
                continue;
            }

            $decoded['conf'] = $newConf;
            $this->update(
                '{{%encounter_definition}}',
                ['workflow_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['id' => (int) $row['id']]
            );
        }
    }

    public function safeDown()
    {
        // Irreversible de forma segura (podría fusionar pasos distintos).
        echo "m260714_143000_split_practicas_indicaciones_workflow cannot be reverted.\n";

        return false;
    }
}
