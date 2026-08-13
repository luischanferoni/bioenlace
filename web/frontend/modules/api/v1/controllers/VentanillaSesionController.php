<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Domain\Person\Service\PersonaBusquedaAsistenteUiService;
use common\components\Domain\Person\Ventanilla\VentanillaSesionService;
use Yii;

/**
 * Sesión temporal de mostrador: el staff actúa por un paciente identificado (turnos).
 *
 * POST /api/v1/ventanilla-sesion/iniciar
 * GET  /api/v1/ventanilla-sesion/estado
 * POST /api/v1/ventanilla-sesion/cerrar
 * GET  /api/v1/ventanilla-sesion/buscar-persona
 *
 * RBAC ApiGhost: /api/ventanilla-sesion/&lt;action&gt;
 */
class VentanillaSesionController extends BaseController
{
    /**
     * Identifica al paciente (conocido / DNI / Didit) e inicia la ventanilla.
     *
     * @action_name Iniciar sesión de ventanilla
     */
    public function actionIniciar(): array
    {
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body) || $body === []) {
            $body = Yii::$app->request->post();
        }
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = (new VentanillaSesionService())->iniciar($body, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 500);
        }

        return $this->success($data, 'Ventanilla iniciada');
    }

    /**
     * Sesión abierta del staff actual, o null si no hay / venció.
     *
     * @action_name Estado de ventanilla
     */
    public function actionEstado(): array
    {
        $data = (new VentanillaSesionService())->estado();

        return $this->success($data, $data === null ? 'Sin ventanilla activa' : 'Ventanilla activa');
    }

    /**
     * Cierra la ventanilla del staff actual.
     *
     * @action_name Cerrar sesión de ventanilla
     */
    public function actionCerrar(): array
    {
        (new VentanillaSesionService())->cerrar();

        return $this->success(null, 'Ventanilla cerrada');
    }

    /**
     * Autocomplete de pacientes conocidos (sin excluir cola de guardia).
     *
     * @action_name Buscar persona para ventanilla
     */
    public function actionBuscarPersona(): array
    {
        $q = Yii::$app->request->get('q');
        $opts = PersonaBusquedaAsistenteUiService::buscar(is_string($q) ? $q : null, 30);
        $results = [];
        foreach ($opts as $opt) {
            $results[] = [
                'id' => (string) ($opt['id'] ?? ''),
                'text' => (string) ($opt['name'] ?? ''),
            ];
        }

        return $this->success(['results' => $results], 'Candidatos');
    }
}
