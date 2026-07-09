<?php

use common\components\Domain\Clinical\Workflow\EncounterDefinitionBootstrapService;
use common\models\Clinical\Encounter;
use yii\db\Migration;
use yii\db\Query;

/**
 * Backfill encounter_definition (tabla vacía tras FHIR sin migración de consultas_configuracion).
 */
class m260709_120000_encounter_definition_backfill extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260709_120000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $table = $this->db->schema->getRawTableName('{{%encounter_definition}}');
        if ($this->db->schema->getTableSchema($table, true) === null) {
            echo "m260709_120000: sin tabla encounter_definition.\n";

            return;
        }

        $bootstrap = new EncounterDefinitionBootstrapService();
        $pairs = [];

        $servicios = (new Query())
            ->select(['id_servicio', 'nombre', 'acepta_turnos'])
            ->from('{{%servicios}}')
            ->all($this->db);
        foreach ($servicios as $row) {
            $serviceId = (int) ($row['id_servicio'] ?? 0);
            if ($serviceId <= 0) {
                continue;
            }
            if (($row['acepta_turnos'] ?? '') === 'SI') {
                $pairs[$serviceId . '|AMB'] = [Encounter::ENCOUNTER_CLASS_AMB, $serviceId];
            }
            $name = mb_strtoupper(trim((string) ($row['nombre'] ?? '')));
            if (str_contains($name, 'GUARDIA') || str_contains($name, 'URGEN')) {
                $pairs[$serviceId . '|EMER'] = [Encounter::ENCOUNTER_CLASS_EMER, $serviceId];
            }
            if (str_contains($name, 'INTERN') || str_contains($name, 'SIA(')) {
                $pairs[$serviceId . '|IMP'] = [Encounter::ENCOUNTER_CLASS_IMP, $serviceId];
            }
        }

        if ($this->db->schema->getTableSchema('{{%encounter}}', true) !== null) {
            $encounterRows = (new Query())
                ->select(['service_id', 'encounter_class'])
                ->from('{{%encounter}}')
                ->where(['not', ['service_id' => null]])
                ->andWhere(['>', 'service_id', 0])
                ->groupBy(['service_id', 'encounter_class'])
                ->all($this->db);
            foreach ($encounterRows as $row) {
                $serviceId = (int) ($row['service_id'] ?? 0);
                $class = trim((string) ($row['encounter_class'] ?? ''));
                if ($serviceId > 0 && $class !== '') {
                    $pairs[$serviceId . '|' . $class] = [$class, $serviceId];
                }
            }
        }

        if ($this->db->schema->getTableSchema('{{%turnos}}', true) !== null) {
            $turnoServices = (new Query())
                ->select(['id_servicio_asignado'])
                ->from('{{%turnos}}')
                ->where(['>', 'id_servicio_asignado', 0])
                ->groupBy(['id_servicio_asignado'])
                ->column($this->db);
            foreach ($turnoServices as $serviceId) {
                $serviceId = (int) $serviceId;
                if ($serviceId > 0) {
                    $pairs[$serviceId . '|AMB'] = [Encounter::ENCOUNTER_CLASS_AMB, $serviceId];
                }
            }
        }

        $created = 0;
        foreach ($pairs as [$encounterClass, $serviceId]) {
            $before = (new Query())->from($table)->where([
                'service_id' => $serviceId,
                'encounter_class' => $encounterClass,
                'deleted_at' => null,
            ])->exists($this->db);
            $definition = $bootstrap->ensureForServiceAndClass($serviceId, $encounterClass);
            if ($definition !== null && !$before) {
                $created++;
            }
        }

        echo "    > encounter_definition backfill: {$created} fila(s) nuevas, " . count($pairs) . " par(es) servicio/clase procesados.\n";
    }

    public function safeDown()
    {
        echo "    > m260709_120000: safeDown no elimina definiciones (pueden haberse editado en admin).\n";
    }
}
