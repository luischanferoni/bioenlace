<?php

namespace console\controllers;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;
use yii\helpers\Console;

/**
 * Utilidades de seed clínico (desarrollo / pruebas móvil paciente).
 */
class ClinicalSeedController extends Controller
{
    private const SEED_TITLE = '[DEV] Care plan demo (app paciente)';

    private const SEED_MARKER = 'seed:m260521_100009_care_plan_demo';

    /** @var int id_persona destino (opción --persona) */
    public $persona = 0;

    public function options($actionID): array
    {
        $opts = parent::options($actionID);
        if ($actionID === 'care-plan-demo-assign') {
            $opts[] = 'persona';
        }

        return $opts;
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['p' => 'persona']);
    }

    /**
     * Muestra el care plan demo y la persona a la que está vinculado.
     */
    public function actionCarePlanDemoInfo(): int
    {
        $plan = $this->findSeedPlanRow();
        if ($plan === null) {
            $this->stderr("No existe care plan con título: " . self::SEED_TITLE . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $personaId = (int) $plan['subject_persona_id'];
        $persona = (new Query())
            ->select(['id_persona', 'id_user', 'nombre', 'apellido', 'documento'])
            ->from('{{%personas}}')
            ->where(['id_persona' => $personaId])
            ->one();

        $this->stdout("Care plan demo id={$plan['id']} status={$plan['status']} category={$plan['category']}\n");
        $this->stdout("subject_persona_id={$personaId}\n");
        if ($persona) {
            $this->stdout(
                "Persona: {$persona['apellido']}, {$persona['nombre']} | id_user={$persona['id_user']} | doc={$persona['documento']}\n",
                Console::FG_CYAN
            );
        }
        $this->stdout(
            "\nGET /api/v1/clinical/care-plans/active filtra por idPersona del JWT.\n"
            . "El paciente móvil debe coincidir con subject_persona_id o reasignar:\n"
            . "  php yii clinical-seed/care-plan-demo-assign --persona=<id_persona>\n",
            Console::FG_YELLOW
        );

        return ExitCode::OK;
    }

    /**
     * Reasigna el seed demo (care plan, encounter, órdenes) a otra persona.
     *
     * Uso: php yii clinical-seed/care-plan-demo-assign 920779
     *   o: php yii clinical-seed/care-plan-demo-assign --persona=920779
     *
     * @param int $personaId id_persona (argumento posicional; si es 0, usa --persona)
     */
    public function actionCarePlanDemoAssign(int $personaId = 0): int
    {
        $persona = $personaId > 0 ? $personaId : (int) $this->persona;
        if ($persona <= 0) {
            $this->stderr("Indicá id_persona: php yii clinical-seed/care-plan-demo-assign 920779\n", Console::FG_RED);
            $this->stderr("  o: php yii clinical-seed/care-plan-demo-assign --persona=920779\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        if (!(new Query())->from('{{%personas}}')->where(['id_persona' => $persona])->exists()) {
            $this->stderr("No existe personas.id_persona={$persona}\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $plan = $this->findSeedPlanRow();
        if ($plan === null) {
            $this->stderr("No existe care plan demo. Ejecutá la migración m260521_100009_seed_care_plan_demo.\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $planId = (int) $plan['id'];
        $encounterId = (int) ($plan['encounter_id'] ?? 0);
        $db = \Yii::$app->db;

        $db->createCommand()->update('{{%care_plan}}', ['subject_persona_id' => $persona], ['id' => $planId])->execute();

        if ($encounterId > 0) {
            $db->createCommand()->update('{{%encounter}}', ['subject_persona_id' => $persona], ['id' => $encounterId])->execute();
        }

        foreach (['{{%medication_request}}', '{{%service_request}}'] as $table) {
            if ($db->schema->getTableSchema($table, true) === null) {
                continue;
            }
            $db->createCommand()->update($table, ['subject_persona_id' => $persona], ['care_plan_id' => $planId])->execute();
        }

        $this->stdout(
            "OK: care plan demo id={$planId} reasignado a id_persona={$persona}.\n",
            Console::FG_GREEN
        );

        return ExitCode::OK;
    }

    /** @return array<string, mixed>|null */
    private function findSeedPlanRow(): ?array
    {
        if (\Yii::$app->db->schema->getTableSchema('{{%care_plan}}', true) === null) {
            return null;
        }

        $row = (new Query())
            ->from('{{%care_plan}}')
            ->where(['title' => self::SEED_TITLE])
            ->andWhere(['deleted_at' => null])
            ->one();

        return $row !== false ? $row : null;
    }
}
