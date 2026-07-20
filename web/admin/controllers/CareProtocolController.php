<?php

namespace admin\controllers;

use common\components\Domain\Clinical\Service\CareProtocolAdminService;
use common\models\Clinical\CareProtocol;
use common\models\Provincia;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * ABM web de protocolos de cuidado (PlanDefinition-lite). Solo superadmin.
 * Llama a CareProtocolAdminService en el mismo proceso (no HTTP/API).
 */
class CareProtocolController extends Controller
{
    private CareProtocolAdminService $admin;

    public function init(): void
    {
        parent::init();
        $this->admin = new CareProtocolAdminService();
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (Yii::$app->user->isGuest || !Yii::$app->user->isSuperadmin) {
            throw new ForbiddenHttpException('Solo superadmin puede administrar protocolos de cuidado.');
        }

        return true;
    }

    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceAdminAccessControl::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'toggle-enabled' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $incluirDeshabilitados = (bool) Yii::$app->request->get('incluir_deshabilitados', true);
        $protocolos = $this->admin->listar($incluirDeshabilitados);
        $provincias = $this->provinciaNombres();

        return $this->render('index', [
            'protocolos' => $protocolos,
            'incluirDeshabilitados' => $incluirDeshabilitados,
            'provincias' => $provincias,
        ]);
    }

    public function actionCreate()
    {
        $model = $this->emptyFormModel();
        if (Yii::$app->request->isPost) {
            try {
                $this->admin->crear($this->payloadFromPost());
                Yii::$app->session->setFlash('success', 'Protocolo creado correctamente.');

                return $this->redirect(['index']);
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
                $model = $this->formModelFromPost();
            } catch (\RuntimeException $e) {
                throw new ForbiddenHttpException($e->getMessage());
            }
        }

        return $this->render('create', [
            'model' => $model,
            'provincias' => $this->provinciaOptions(),
        ]);
    }

    public function actionUpdate(int $id)
    {
        try {
            $row = $this->admin->obtener($id);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }

        $model = $this->formModelFromAdmin($row);
        if (Yii::$app->request->isPost) {
            try {
                $this->admin->actualizar($id, $this->payloadFromPost());
                Yii::$app->session->setFlash('success', 'Protocolo actualizado correctamente.');

                return $this->redirect(['index']);
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
                $model = $this->formModelFromPost();
                $model['id'] = $id;
            } catch (\RuntimeException $e) {
                throw new ForbiddenHttpException($e->getMessage());
            }
        }

        return $this->render('update', [
            'model' => $model,
            'provincias' => $this->provinciaOptions(),
        ]);
    }

    public function actionToggleEnabled(int $id)
    {
        $activar = (bool) Yii::$app->request->post('activar', 0);
        try {
            if ($activar) {
                $this->admin->activar($id);
                Yii::$app->session->setFlash('success', 'Protocolo activado.');
            } else {
                $this->admin->desactivar($id);
                Yii::$app->session->setFlash('success', 'Protocolo desactivado.');
            }
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            throw new ForbiddenHttpException($e->getMessage());
        }

        return $this->redirect(['index']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromPost(): array
    {
        $post = Yii::$app->request->post();
        $sex = $post['sex'] ?? [];
        if (!is_array($sex)) {
            $sex = [];
        }
        $codesRaw = (string) ($post['condition_codes_text'] ?? '');
        $codes = [];
        foreach (preg_split('/[\s,;]+/', $codesRaw) ?: [] as $c) {
            $c = trim($c);
            if ($c !== '') {
                $codes[] = $c;
            }
        }
        $actionsJson = trim((string) ($post['actions_json'] ?? ''));
        $actions = json_decode($actionsJson, true);
        if (!is_array($actions)) {
            throw new \InvalidArgumentException('actions_json no es un JSON válido de lista.');
        }

        return [
            'protocol_key' => trim((string) ($post['protocol_key'] ?? '')),
            'title' => trim((string) ($post['title'] ?? '')),
            'hub_label' => trim((string) ($post['hub_label'] ?? '')),
            'orden' => (int) ($post['orden'] ?? 100),
            'enabled' => !empty($post['enabled']),
            'scope_type' => strtoupper(trim((string) ($post['scope_type'] ?? CareProtocol::SCOPE_NATION))),
            'id_provincia' => ($post['id_provincia'] ?? '') !== '' ? (int) $post['id_provincia'] : null,
            'age_min' => ($post['age_min'] ?? '') !== '' ? (int) $post['age_min'] : null,
            'age_max' => ($post['age_max'] ?? '') !== '' ? (int) $post['age_max'] : null,
            'sex' => $sex,
            'condition_codes' => $codes,
            'condition_match' => strtolower(trim((string) ($post['condition_match'] ?? CareProtocol::MATCH_NONE))),
            'actions' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFormModel(): array
    {
        $defaultActions = [
            [
                'code' => 'solicitar_turno',
                'label' => 'Pedir turno de control',
                'description' => '',
                'outcome' => 'modalidad',
                'draft' => ['triage_raiz' => 'seguimiento_cronico'],
            ],
            [
                'code' => 'consulta_mensaje',
                'label' => 'Consulta por mensaje',
                'description' => '',
                'outcome' => 'captura_mensaje',
                'draft' => ['intake_tipo' => 'consulta_general'],
            ],
        ];

        return [
            'id' => null,
            'protocol_key' => '',
            'title' => '',
            'hub_label' => '',
            'orden' => 100,
            'enabled' => true,
            'scope_type' => CareProtocol::SCOPE_NATION,
            'id_provincia' => null,
            'age_min' => null,
            'age_max' => null,
            'sex' => [],
            'condition_codes_text' => '',
            'condition_match' => CareProtocol::MATCH_NONE,
            'actions_json' => json_encode($defaultActions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formModelFromAdmin(array $row): array
    {
        $codes = $row['condition_codes'] ?? [];
        $actions = $row['actions'] ?? [];

        return [
            'id' => (int) ($row['id'] ?? 0),
            'protocol_key' => (string) ($row['protocol_key'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'hub_label' => (string) ($row['hub_label'] ?? ''),
            'orden' => (int) ($row['orden'] ?? 100),
            'enabled' => (bool) ($row['enabled'] ?? true),
            'scope_type' => (string) ($row['scope_type'] ?? CareProtocol::SCOPE_NATION),
            'id_provincia' => $row['id_provincia'] ?? null,
            'age_min' => $row['age_min'] ?? null,
            'age_max' => $row['age_max'] ?? null,
            'sex' => is_array($row['sex'] ?? null) ? $row['sex'] : [],
            'condition_codes_text' => is_array($codes) ? implode(', ', $codes) : '',
            'condition_match' => (string) ($row['condition_match'] ?? CareProtocol::MATCH_NONE),
            'actions_json' => json_encode($actions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formModelFromPost(): array
    {
        $post = Yii::$app->request->post();
        $sex = $post['sex'] ?? [];
        if (!is_array($sex)) {
            $sex = [];
        }

        return [
            'id' => null,
            'protocol_key' => (string) ($post['protocol_key'] ?? ''),
            'title' => (string) ($post['title'] ?? ''),
            'hub_label' => (string) ($post['hub_label'] ?? ''),
            'orden' => (int) ($post['orden'] ?? 100),
            'enabled' => !empty($post['enabled']),
            'scope_type' => (string) ($post['scope_type'] ?? CareProtocol::SCOPE_NATION),
            'id_provincia' => ($post['id_provincia'] ?? '') !== '' ? (int) $post['id_provincia'] : null,
            'age_min' => ($post['age_min'] ?? '') !== '' ? (int) $post['age_min'] : null,
            'age_max' => ($post['age_max'] ?? '') !== '' ? (int) $post['age_max'] : null,
            'sex' => $sex,
            'condition_codes_text' => (string) ($post['condition_codes_text'] ?? ''),
            'condition_match' => (string) ($post['condition_match'] ?? CareProtocol::MATCH_NONE),
            'actions_json' => (string) ($post['actions_json'] ?? ''),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function provinciaOptions(): array
    {
        $rows = Provincia::find()->orderBy(['nombre' => SORT_ASC])->asArray()->all();

        return ['' => '— Seleccionar provincia —'] + ArrayHelper::map($rows, 'id_provincia', 'nombre');
    }

    /**
     * @return array<int, string>
     */
    private function provinciaNombres(): array
    {
        $rows = Provincia::find()->asArray()->all();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id_provincia']] = (string) $r['nombre'];
        }

        return $out;
    }
}
