<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use common\components\Domain\Person\Service\PacienteContextoService;
use common\components\Domain\Person\Service\ProvinciaSuggestionService;
use common\components\Domain\Person\Service\ProvincialResourceLookupService;

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
     * Devuelve todas las provincias ordenadas por proximidad a la IP del cliente.
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

    /**
     * GET|POST /api/v1/paciente-contexto/buscar-recurso-provincial-como-paciente
     *
     * Query: q (texto libre, ej. "ministerio de salud"), tipo (opcional, ej. ministerio_salud).
     *
     * @action_name Buscar recurso provincial
     * @entity PacienteContexto
     * @tags paciente, contexto, geografía
     */
    public function actionBuscarRecursoProvincialComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        $ctx = (new PacienteContextoService())->getOrCreate($idPersona);
        if (!$ctx->puedeOperarApp()) {
            throw new BadRequestHttpException('Definí tu provincia de contexto para consultar recursos locales.');
        }

        $params = $this->mergedParams();
        $query = trim((string) ($params['q'] ?? $params['query'] ?? ''));
        $tipo = trim((string) ($params['tipo'] ?? ''));

        $result = (new ProvincialResourceLookupService())->findForProvincia(
            (int) $ctx->id_provincia_contexto,
            $tipo,
            $query !== '' ? $query : null
        );

        if (Yii::$app->request->isPost) {
            return $this->buildRecursoSubmitEnvelope($result);
        }

        if ($result === null) {
            return [
                'success' => true,
                'data' => [
                    'encontrado' => false,
                    'mensaje' => 'No encontramos ese recurso para tu provincia de contexto.',
                ],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'encontrado' => true,
                'recurso' => $result,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $result
     * @return array<string, mixed>
     */
    private function buildRecursoSubmitEnvelope(?array $result): array
    {
        if ($result === null) {
            return [
                'kind' => 'ui_submit_result',
                'success' => true,
                'data' => [
                    'mensaje' => 'No encontramos ese recurso para tu provincia de contexto.',
                    'encontrado' => false,
                ],
            ];
        }

        $row = $result['recurso'] ?? [];
        if (!is_array($row)) {
            $row = [];
        }
        $nombre = trim((string) ($row['nombre'] ?? ''));
        $direccion = trim((string) ($row['direccion'] ?? ''));
        $telefono = trim((string) ($row['telefono'] ?? ''));
        $provincia = trim((string) ($result['provincia'] ?? ''));

        $lines = [];
        if ($nombre !== '') {
            $lines[] = $nombre . ($provincia !== '' ? " ($provincia)" : '');
        }
        if ($direccion !== '') {
            $lines[] = 'Dirección: ' . $direccion;
        }
        if ($telefono !== '') {
            $lines[] = 'Teléfono: ' . $telefono;
        }

        return [
            'kind' => 'ui_submit_result',
            'success' => true,
            'data' => [
                'mensaje' => $lines !== [] ? implode("\n", $lines) : 'Recurso encontrado.',
                'encontrado' => true,
                'recurso' => $result,
            ],
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
