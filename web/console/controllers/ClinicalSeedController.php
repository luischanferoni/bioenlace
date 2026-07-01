<?php

namespace console\controllers;

use common\components\CrearUsuarioDePruebaHelper;
use common\components\Domain\Clinical\Laboratory\Service\LaboratoryDemoSeedService;
use common\components\Domain\Clinical\Laboratory\Service\LaboratoryResultQueryService;
use common\components\Domain\Clinical\CarePlan\Reminder\CarePlanReminderDemoTimingService;
use common\components\Domain\Clinical\Prescription\Service\ElectronicPrescriptionDemoSeedService;
use common\components\Domain\Clinical\Prescription\Support\PrescriptionDocumentSupport;
use common\components\Domain\Organization\Service\Seed\EfectorDemoSeedService;
use common\components\Domain\Organization\Service\Seed\MedicoMedGeneralEfectorSeedService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;
use yii\helpers\Console;

/**
 * Seeds de desarrollo: clínico, organización (PES/médicos) y usuarios de prueba.
 *
 * Uso: php yii clinical-seed/<acción>  (ver acciones en este controller)
 */
class ClinicalSeedController extends Controller
{
    private const SEED_TITLE = '[DEV] Care plan demo (app paciente)';

    private const SEED_MARKER = 'seed:m260521_100009_care_plan_demo';

    /** @var int id_persona destino (opción --persona) */
    public $persona = 0;

    /** @var int id_efector (seeds de médico MED GENERAL) */
    public $efector = 863;

    /** @var int 1 = crear agenda laboral básica en seed médico */
    public $agenda = 1;

    /** @var string contraseña del usuario médico seed (vacío = default) */
    public $password = '';

    /** @var string contraseña cuenta play review paciente (vacío = default) */
    public $playPassword = '';

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
            'care-plan-reminder-demo',
        ], true)) {
            $opts[] = 'persona';
        }
        if (in_array($actionID, [
            'medico-med-general',
            'medico-med-general-remove',
            'medico-med-general-info',
            'efector-demo-contexto',
            'efector-demo-contexto-remove',
        ], true)) {
            $opts[] = 'efector';
            $opts[] = 'agenda';
            $opts[] = 'password';
        }
        if ($actionID === 'play-review-paciente') {
            $opts[] = 'playPassword';
        }

        return $opts;
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'p' => 'persona',
            'e' => 'efector',
            'a' => 'agenda',
        ]);
    }

    /**
     * Crea efector público en Santa Fe y clínica privada en Santiago del Estero, cada uno con médico MED GENERAL.
     *
     * php yii clinical-seed/efector-demo-contexto
     * php yii clinical-seed/efector-demo-contexto --agenda=0
     */
    public function actionEfectorDemoContexto(): int
    {
        $withAgenda = (int) $this->agenda !== 0;

        try {
            $result = (new EfectorDemoSeedService())->upsertAll(true, $withAgenda);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        foreach (['public' => 'Público (otra provincia)', 'private' => 'Privado'] as $key => $label) {
            $row = $result[$key];
            $verb = !empty($row['created']) ? 'creado' : 'actualizado';
            $this->stdout(
                "{$label}: efector {$verb} id={$row['id_efector']} | {$row['nombre']} | "
                . "{$row['provincia']} | financiamiento={$row['origen_financiamiento']}\n",
                Console::FG_GREEN
            );
            if (isset($row['medico'])) {
                $med = $row['medico'];
                $this->stdout(
                    "  médico: user={$med['username']} | doc={$med['documento']} | password: {$med['password']}\n",
                    Console::FG_CYAN
                );
            }
        }

        $this->stdout(
            "\nAdmin: filtrá por provincia o sector en /admin/efectores.\n"
            . "Paciente: contexto PUBLICO + provincia del CAP demo / PRIVADO + provincia del efector "
            . EfectorDemoSeedService::DEFAULT_EFECTOR_REF . " para probar offering.\n",
            Console::FG_YELLOW
        );

        return ExitCode::OK;
    }

    public function actionEfectorDemoContextoInfo(): int
    {
        $service = new EfectorDemoSeedService();
        $medicoSeed = new MedicoMedGeneralEfectorSeedService();
        $found = false;

        foreach (
            [
                EfectorDemoSeedService::COD_SISA_PUBLIC_OTRA_PROV => 'Público Santa Fe',
                EfectorDemoSeedService::COD_SISA_PRIVATE => 'Clínica privada',
            ] as $codigo => $label
        ) {
            $row = $service->findByCodigoSisa($codigo);
            if ($row === null) {
                $this->stdout("{$label}: no existe (codigo_sisa={$codigo}).\n", Console::FG_YELLOW);
                continue;
            }

            $found = true;
            $idEfector = (int) $row['id_efector'];
            $this->stdout(
                "{$label}: id={$idEfector} | {$row['nombre']} | {$row['origen_financiamiento']}\n",
                Console::FG_CYAN
            );
            $med = $medicoSeed->findSeedRow($idEfector);
            if ($med === null || !isset($med['pes'])) {
                $this->stdout("  Sin médico MED GENERAL. Ejecutá: php yii clinical-seed/efector-demo-contexto\n");
            } else {
                $p = $med['persona'];
                $this->stdout("  Médico: {$p['apellido']}, {$p['nombre']} | doc={$p['documento']}\n");
            }
        }

        if (!$found) {
            $this->stderr("No hay efectores demo. Ejecutá: php yii clinical-seed/efector-demo-contexto\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        return ExitCode::OK;
    }

    public function actionEfectorDemoContextoRemove(): int
    {
        $result = (new EfectorDemoSeedService())->removeAll(true);
        if (!$result['removed_public'] && !$result['removed_private']) {
            $this->stderr("No se encontraron efectores demo para eliminar.\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $this->stdout("Efectores demo eliminados (PES/agenda médicos incluidos).\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Crea persona + usuario de prueba genérico (documento {@see CrearUsuarioDePruebaHelper::DOCUMENTO}).
     */
    public function actionUsuarioDePrueba(): int
    {
        $this->stdout(
            'Creando usuario de prueba (documento ' . CrearUsuarioDePruebaHelper::DOCUMENTO . ")...\n",
            Console::FG_YELLOW
        );

        $result = CrearUsuarioDePruebaHelper::crear();

        if (!$result['ok']) {
            $this->stderr($result['message'] . "\n", Console::FG_RED);
            if (!empty($result['errors'])) {
                $this->stderr(print_r($result['errors'], true) . "\n", Console::FG_RED);
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout($result['message'] . "\n", Console::FG_GREEN);
        $this->stdout('Persona: ' . json_encode($result['persona'], JSON_UNESCAPED_UNICODE) . "\n");
        $this->stdout('Usuario: ' . json_encode($result['user'], JSON_UNESCAPED_UNICODE) . "\n");

        return ExitCode::OK;
    }

    /**
     * Cuenta paciente para revisión Google Play ({@see PlayReviewPacienteSeedService}).
     *
     * php yii clinical-seed/play-review-paciente
     * php yii clinical-seed/play-review-paciente --playPassword='TuClaveSegura1!'
     */
    public function actionPlayReviewPaciente(): int
    {
        $service = new \common\components\Domain\Person\Service\Seed\PlayReviewPacienteSeedService();
        $plain = trim((string) $this->playPassword) !== '' ? (string) $this->playPassword : null;

        try {
            $result = $service->upsert($plain);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $this->stdout("Cuenta play review paciente lista.\n", Console::FG_GREEN);
        $this->stdout("Usuario: {$result['username']} | doc {$result['documento']} | password: {$result['password']}\n", Console::FG_CYAN);
        $this->stdout("Agregá este usuario a play_review_accounts en params-local y play_review_login_habilitado=true.\n");

        return ExitCode::OK;
    }

    /**
     * Carga las 24 jurisdicciones argentinas en {{%provincias}} (idempotente).
     *
     * php yii clinical-seed/provincias-argentina
     */
    public function actionProvinciasArgentina(): int
    {
        try {
            $result = (new \common\components\Domain\Person\Service\Seed\ProvinciasArgentinaSeedService())->upsertAll();
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $this->stdout(
            sprintf(
                "Provincias Argentina: %d en catálogo, %d insertadas, %d actualizadas.\n",
                $result['total'],
                $result['inserted'],
                $result['updated']
            ),
            Console::FG_GREEN
        );

        return ExitCode::OK;
    }

    /**
     * Crea médico de prueba en MED GENERAL para el efector indicado (default 863).
     *
     * php yii clinical-seed/medico-med-general
     * php yii clinical-seed/medico-med-general --efector=863 --agenda=1
     */
    public function actionMedicoMedGeneral(): int
    {
        $service = new MedicoMedGeneralEfectorSeedService();
        $idEfector = (int) $this->efector;
        $withAgenda = (int) $this->agenda !== 0;
        $plainPassword = trim((string) $this->password) !== '' ? (string) $this->password : null;

        try {
            $result = $service->upsert($idEfector, $withAgenda, $plainPassword);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $this->stdout($result['message'] . "\n", Console::FG_GREEN);
        $this->stdout("Efector id={$result['id_efector']} | servicio MED GENERAL id={$result['id_servicio']}\n");
        $this->stdout("PES id={$result['id_pes']} | persona id={$result['id_persona']} | user id={$result['id_user']}\n");
        $this->stdout(
            "Login: {$result['username']} | documento {$result['documento']} | password: {$result['password']}\n",
            Console::FG_CYAN
        );
        if ($result['created_servicio_efector']) {
            $this->stdout("  + servicios_efector habilitado para MED GENERAL en el efector.\n");
        }
        if ($result['created_agenda']) {
            $this->stdout("  + agenda laboral Lun–Vie 08–17 creada.\n");
        }
        $this->stdout(
            "\nTras login, fijar sesión operativa con set-session / efector {$idEfector} y servicio MED GENERAL.\n"
        );

        return ExitCode::OK;
    }

    public function actionMedicoMedGeneralInfo(): int
    {
        $service = new MedicoMedGeneralEfectorSeedService();
        $idEfector = (int) $this->efector;
        $row = $service->findSeedRow($idEfector);

        if ($row === null || !isset($row['persona'])) {
            $expected = MedicoMedGeneralEfectorSeedService::expectedIdentity($idEfector);
            $this->stderr(
                "No hay médico seed (doc {$expected['documento']}, user {$expected['username']}) para efector {$idEfector}.\n",
                Console::FG_RED
            );
            $this->stdout("Ejecutá: php yii clinical-seed/medico-med-general --efector={$idEfector}\n");

            return ExitCode::DATAERR;
        }

        $p = $row['persona'];
        $this->stdout("Persona id={$p['id_persona']} | {$p['apellido']}, {$p['nombre']} | doc={$p['documento']} | id_user={$p['id_user']}\n");
        if ($row['pes']) {
            $pes = $row['pes'];
            $this->stdout("PES id={$pes['id']} | efector={$pes['id_efector']} | servicio={$pes['id_servicio']}\n", Console::FG_CYAN);
        } else {
            $this->stdout("Sin PES MED GENERAL en efector {$idEfector}. Ejecutá medico-med-general para completar.\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }

    public function actionMedicoMedGeneralRemove(): int
    {
        $service = new MedicoMedGeneralEfectorSeedService();
        $removed = $service->remove((int) $this->efector);
        if (!$removed) {
            $this->stderr("No se encontró médico seed para efector {$this->efector}.\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $this->stdout("PES y agenda del médico seed eliminados (persona/usuario conservados).\n", Console::FG_GREEN);

        return ExitCode::OK;
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

    /**
     * Añade dosage_json.timing a medicación del care plan demo (recordatorios locales).
     *
     * Uso: php yii clinical-seed/care-plan-reminder-demo --persona=920779
     */
    public function actionCarePlanReminderDemo(int $personaId = 0): int
    {
        $persona = $personaId > 0 ? $personaId : (int) $this->persona;
        if ($persona <= 0) {
            $this->stderr("Indicá id_persona: php yii clinical-seed/care-plan-reminder-demo 920779\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $result = (new CarePlanReminderDemoTimingService())->applyTimingToDemoCarePlan($persona);
        if (($result['care_plan_id'] ?? null) === null) {
            $this->stderr(
                "No hay care plan demo para id_persona={$persona}. Ejecutá migración m260521_100009 o care-plan-demo-assign.\n",
                Console::FG_RED
            );

            return ExitCode::DATAERR;
        }

        $this->stdout(
            'OK: timing medication=' . ($result['updated_medication'] ?? 0)
            . ', service=' . ($result['updated_service'] ?? 0)
            . ", care_plan_id={$result['care_plan_id']}.\n",
            Console::FG_GREEN
        );
        $this->stdout(
            "GET /api/v1/clinical/care-plans/recordatorios-como-paciente\n",
            Console::FG_YELLOW
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
