<?php

namespace console\controllers;

use common\components\Clinical\Laboratory\Service\LaboratoryDemoSeedService;
use common\components\Clinical\Laboratory\Service\LaboratoryResultQueryService;
use common\components\Clinical\Prescription\Service\ElectronicPrescriptionDemoSeedService;
use common\components\Clinical\Prescription\Support\PrescriptionDocumentSupport;
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
        if (in_array($actionID, [
            'care-plan-demo-assign',
            'laboratory-demo',
            'laboratory-demo-remove',
            'laboratory-demo-info',
            'prescription-demo',
            'prescription-demo-remove',
            'prescription-demo-info',
        ], true)) {
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

    /**
     * Crea o actualiza un informe de laboratorio demo para pruebas (lista, detalle, PDF).
     *
     * Uso: php yii clinical-seed/laboratory-demo 920779
     *   o: php yii clinical-seed/laboratory-demo --persona=920779
     *
     * @param int $personaId id_persona (argumento posicional; si es 0, usa --persona)
     */
    public function actionLaboratoryDemo(int $personaId = 0): int
    {
        $persona = $personaId > 0 ? $personaId : (int) $this->persona;
        if ($persona <= 0) {
            $this->stderr("Indicá id_persona: php yii clinical-seed/laboratory-demo 920779\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        try {
            $result = (new LaboratoryDemoSeedService())->upsertForPersona($persona);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $verb = $result['created'] ? 'creado' : 'actualizado';
        $this->stdout(
            "OK: informe demo {$verb} id={$result['report_id']} para id_persona={$persona} "
            . "({$result['observations']} analitos).\n",
            Console::FG_GREEN
        );
        $this->stdout(
            "Probá con JWT del paciente (idPersona={$persona}):\n"
            . "  GET /api/v1/clinical/laboratory-result/mis-resultados-como-paciente\n"
            . "  Flow asistente: laboratorio.ver-resultados-como-paciente\n",
            Console::FG_YELLOW
        );

        return ExitCode::OK;
    }

    /**
     * Lista informes de laboratorio guardados para una persona.
     *
     * @param int $personaId id_persona
     */
    public function actionLaboratoryDemoInfo(int $personaId = 0): int
    {
        $persona = $personaId > 0 ? $personaId : (int) $this->persona;
        if ($persona <= 0) {
            $this->stderr("Indicá id_persona: php yii clinical-seed/laboratory-demo-info 920779\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        if (!(new Query())->from('{{%personas}}')->where(['id_persona' => $persona])->exists()) {
            $this->stderr("No existe personas.id_persona={$persona}\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $reports = (new LaboratoryResultQueryService())->listForPersona($persona);
        if ($reports === []) {
            $this->stdout("Sin informes para id_persona={$persona}. Ejecutá: php yii clinical-seed/laboratory-demo {$persona}\n");

            return ExitCode::OK;
        }

        foreach ($reports as $r) {
            $this->stdout(
                sprintf(
                    "- id=%s | %s | issued=%s | source=%s | obs=%d\n",
                    (string) ($r['id'] ?? ''),
                    (string) ($r['display'] ?? ''),
                    (string) ($r['issuedAt'] ?? ''),
                    (string) ($r['sourceSystem'] ?? ''),
                    is_array($r['observations'] ?? null) ? count($r['observations']) : 0
                ),
                Console::FG_CYAN
            );
        }

        return ExitCode::OK;
    }

    /**
     * Elimina el informe demo (source=demo, external_id seed-lab-demo-{persona}).
     *
     * @param int $personaId id_persona
     */
    public function actionLaboratoryDemoRemove(int $personaId = 0): int
    {
        $persona = $personaId > 0 ? $personaId : (int) $this->persona;
        if ($persona <= 0) {
            $this->stderr("Indicá id_persona: php yii clinical-seed/laboratory-demo-remove 920779\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $removed = (new LaboratoryDemoSeedService())->removeForPersona($persona);
        if (!$removed) {
            $this->stdout("No había informe demo para id_persona={$persona}.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        $this->stdout("OK: informe demo eliminado para id_persona={$persona}.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Crea o actualiza una receta electrónica emitida demo (lista, detalle, PDF, QR).
     *
     * Uso: php yii clinical-seed/prescription-demo 920779
     */
    public function actionPrescriptionDemo(int $personaId = 0): int
    {
        $persona = $personaId > 0 ? $personaId : (int) $this->persona;
        if ($persona <= 0) {
            $this->stderr("Indicá id_persona: php yii clinical-seed/prescription-demo 920779\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        try {
            $result = (new ElectronicPrescriptionDemoSeedService())->upsertForPersona($persona);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $verb = $result['created'] ? 'creada' : 'actualizada';
        $this->stdout(
            "OK: receta demo {$verb} id={$result['prescription_id']} número={$result['prescription_number']} "
            . "para id_persona={$persona}.\n",
            Console::FG_GREEN
        );
        $verifyBase = PrescriptionDocumentSupport::resolveVerificationPublicBaseUrl();
        if ($verifyBase === null) {
            $this->stdout(
                "QR en PDF: definí recetaDigitalRepository.verificationPublicBaseUrl en params-local.php "
                . "(ej. https://tu-host/api/v1).\n",
                Console::FG_YELLOW
            );
        }
        $this->stdout(
            "Probá con JWT del paciente (idPersona={$persona}):\n"
            . "  GET /api/v1/clinical/electronic-prescription/mis-recetas-como-paciente\n"
            . "  Flow asistente: receta.ver-recetas-como-paciente\n",
            Console::FG_YELLOW
        );

        return ExitCode::OK;
    }

    /**
     * Lista recetas emitidas de una persona.
     */
    public function actionPrescriptionDemoInfo(int $personaId = 0): int
    {
        $persona = $personaId > 0 ? $personaId : (int) $this->persona;
        if ($persona <= 0) {
            $this->stderr("Indicá id_persona: php yii clinical-seed/prescription-demo-info 920779\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $rows = (new ElectronicPrescriptionDemoSeedService())->listIssuedForPersona($persona);
        if ($rows === []) {
            $this->stdout("Sin recetas emitidas para id_persona={$persona}. Ejecutá: php yii clinical-seed/prescription-demo {$persona}\n");

            return ExitCode::OK;
        }

        foreach ($rows as $r) {
            $demo = !empty($r['is_demo']) ? ' [demo]' : '';
            $this->stdout(
                sprintf(
                    "- id=%d | %s | issued=%s | %s%s\n",
                    (int) $r['id'],
                    (string) ($r['prescription_number'] ?? ''),
                    (string) ($r['issued_at'] ?? ''),
                    (string) ($r['diagnosis_display'] ?? ''),
                    $demo
                ),
                Console::FG_CYAN
            );
        }

        return ExitCode::OK;
    }

    /**
     * Elimina la receta demo (número DEV-RX-{persona}).
     */
    public function actionPrescriptionDemoRemove(int $personaId = 0): int
    {
        $persona = $personaId > 0 ? $personaId : (int) $this->persona;
        if ($persona <= 0) {
            $this->stderr("Indicá id_persona: php yii clinical-seed/prescription-demo-remove 920779\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $removed = (new ElectronicPrescriptionDemoSeedService())->removeForPersona($persona);
        if (!$removed) {
            $this->stdout("No había receta demo para id_persona={$persona}.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        $this->stdout("OK: receta demo eliminada para id_persona={$persona}.\n", Console::FG_GREEN);

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
