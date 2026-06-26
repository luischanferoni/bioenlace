<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use common\components\Domain\Person\Service\PacienteContextoService;
use common\components\Domain\Person\Service\ProvinciaSuggestionService;

/**
 * Contexto operativo persistente del paciente (sector salud, provincia de contexto).
 *
 * RBAC ApiGhost: /api/paciente-contexto/&lt;action&gt;
 */
class PacienteContextoController extends BaseController
{
    /**
     * GET|POST /api/v1/paciente-contexto/obtener-como-paciente
     *
     * @action_name Obtener contexto paciente
     * @entity PacienteContexto
     * @tags paciente, contexto
     */
    public function actionObtenerComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        $service = new PacienteContextoService();
        $ctx = $service->getOrCreate($idPersona);

        return [
            'success' => true,
            'data' => ['contexto' => $service->export($ctx)],
        ];
    }

    /**
     * POST /api/v1/paciente-contexto/actualizar-como-paciente
     *
     * Body: sector_salud (publico|privado), id_provincia_contexto (opcional).
     *
     * @action_name Actualizar contexto paciente
     * @entity PacienteContexto
     * @tags paciente, contexto
     */
    public function actionActualizarComoPaciente(): array
    {
        $this->assertPost();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        try {
            $contexto = (new PacienteContextoService())->actualizar(
                $idPersona,
                $this->mergedParams()
            );

            return [
                'success' => true,
                'data' => ['contexto' => $contexto],
            ];
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * GET|POST /api/v1/paciente-contexto/sugerir-provincias-como-paciente
     *
     * Devuelve hasta 5 provincias sugeridas según IP del cliente.
     *
     * @action_name Sugerir provincias por IP
     * @entity PacienteContexto
     * @tags paciente, contexto, provincia
     */
    public function actionSugerirProvinciasComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        $provincias = (new ProvinciaSuggestionService())->sugerirPorIp();

        return [
            'success' => true,
            'data' => ['provincias' => $provincias],
        ];
    }

    private function assertPost(): void
    {
        if (!Yii::$app->request->isPost) {
            throw new MethodNotAllowedHttpException(['POST']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedParams(): array
    {
        return array_merge(Yii::$app->request->get(), Yii::$app->request->post());
    }
}
