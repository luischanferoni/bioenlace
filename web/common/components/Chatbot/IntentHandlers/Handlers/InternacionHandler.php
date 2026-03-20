<?php

namespace common\components\Chatbot\IntentHandlers\Handlers;

use Yii;
use common\components\Chatbot\ConversationContext;
use common\components\Actions\UniversalQueryAgent;
use common\models\SegNivelInternacion;

class InternacionHandler extends BaseIntentHandler
{
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent, 'parameters' => $parameters]);

        switch ($intent) {
            case 'internacion_ingreso':
                return $this->handleIngreso($message, $parameters, $context, $userId);

            case 'internacion_ver_actual':
                return $this->handleVerActual($message, $parameters, $context, $userId);

            case 'internacion_alta':
                return $this->handleAlta($message, $parameters, $context, $userId);

            case 'internacion_cambio_cama':
                return $this->handleCambioCama($message, $parameters, $context, $userId);

            case 'internacion_indicar_medicacion':
                return $this->handleIndicarMedicacion($message, $parameters, $context, $userId);

            case 'internacion_agregar_diagnostico':
                return $this->handleAgregarDiagnostico($message, $parameters, $context, $userId);

            case 'internacion_agregar_practica':
                return $this->handleAgregarPractica($message, $parameters, $context, $userId);

            case 'estado_internacion':
                return $this->handleEstado($message, $parameters, $context, $userId);

            default:
                return $this->generateErrorResponse("Intent '{$intent}' no manejado por InternacionHandler");
        }
    }

    private function handleIngreso($message, $parameters, $context, $userId)
    {
        // Para ingreso, si no hay persona explícita, el cliente puede usar el paciente en sesión.
        $context = $this->updateContext($userId, $context, 'internacion_ingreso', $parameters);
        $context = ConversationContext::markCompleted($context);

        $text = 'Listo. Abrí el formulario de ingreso a internación.';

        $actions = $this->actionsFromAgent(
            "internación ingreso" . (!empty($parameters['id_persona']) ? (" paciente " . (int)$parameters['id_persona']) : '') .
            (!empty($parameters['id_cama']) ? (" cama " . (int)$parameters['id_cama']) : ''),
            $userId
        );

        if (empty($actions)) {
            $actions = [[
                'type' => 'open_route',
                'title' => 'Ingresar a internación',
                'route' => $this->route('/internacion/create'),
                'params' => array_filter([
                    'id_persona' => $parameters['id_persona'] ?? null,
                    'id' => $parameters['id_cama'] ?? null,
                ], fn($v) => $v !== null && $v !== ''),
            ]];
        }

        return $this->generateSuccessResponse($text, [], $actions);
    }

    private function handleVerActual($message, $parameters, $context, $userId)
    {
        $idInternacion = $this->resolveInternacionId($parameters);
        if (!$idInternacion) {
            $missing = $this->getMissingRequiredParams('internacion_ver_actual', $parameters);
            // Forzar pregunta por paciente si no podemos resolver internación
            if (!in_array('id_persona', $missing, true) && empty($parameters['id_persona'])) {
                $missing[] = 'id_persona';
            }
            $context = $this->updateContext($userId, $context, 'internacion_ver_actual', $parameters);
            $context = ConversationContext::setAwaitingInput($context, $missing[0] ?? 'id_persona');
            $resp = $this->generateMissingParamsResponse($missing, 'internacion_ver_actual');
            $resp['context_update'] = $context;
            return $resp;
        }

        $context = $this->updateContext($userId, $context, 'internacion_ver_actual', array_merge($parameters, ['id_internacion' => $idInternacion]));
        $context = ConversationContext::markCompleted($context);

        $actions = $this->actionsFromAgent("ver internación " . (int)$idInternacion, $userId);
        if (empty($actions)) {
            $actions = [[
                'type' => 'open_route',
                'title' => 'Ver internación',
                'route' => $this->route('/internacion/view'),
                'params' => ['id' => (int)$idInternacion],
            ]];
        }

        return $this->generateSuccessResponse('Abrí la internación activa.', [], $actions);
    }

    private function handleAlta($message, $parameters, $context, $userId)
    {
        $idInternacion = $this->resolveInternacionId($parameters);
        if (!$idInternacion) {
            $context = $this->updateContext($userId, $context, 'internacion_alta', $parameters);
            $context = ConversationContext::setAwaitingInput($context, 'id_internacion');
            $resp = $this->generateMissingParamsResponse(['id_internacion'], 'internacion_alta');
            $resp['context_update'] = $context;
            return $resp;
        }

        $context = $this->updateContext($userId, $context, 'internacion_alta', array_merge($parameters, ['id_internacion' => $idInternacion]));
        $context = ConversationContext::markCompleted($context);

        $actions = $this->actionsFromAgent("alta internación " . (int)$idInternacion, $userId);
        if (empty($actions)) {
            $actions = [[
                'type' => 'open_route',
                'title' => 'Dar alta',
                'route' => $this->route('/internacion/update'),
                'params' => ['id' => (int)$idInternacion],
            ]];
        }

        return $this->generateSuccessResponse('Abrí el formulario de alta/externación.', [], $actions);
    }

    private function handleCambioCama($message, $parameters, $context, $userId)
    {
        $idInternacion = $this->resolveInternacionId($parameters);
        if (!$idInternacion) {
            $context = $this->updateContext($userId, $context, 'internacion_cambio_cama', $parameters);
            $context = ConversationContext::setAwaitingInput($context, 'id_internacion');
            $resp = $this->generateMissingParamsResponse(['id_internacion'], 'internacion_cambio_cama');
            $resp['context_update'] = $context;
            return $resp;
        }

        $context = $this->updateContext($userId, $context, 'internacion_cambio_cama', array_merge($parameters, ['id_internacion' => $idInternacion]));
        $context = ConversationContext::markCompleted($context);

        $actions = $this->actionsFromAgent(
            "cambio cama internación " . (int)$idInternacion . (!empty($parameters['id_cama']) ? (" cama " . (int)$parameters['id_cama']) : ''),
            $userId
        );
        if (empty($actions)) {
            $actions = [[
                'type' => 'open_route',
                'title' => 'Cambio de cama',
                'route' => $this->route('/internacion-hcama/create'),
                'params' => ['id' => (int)$idInternacion],
            ]];
        }

        return $this->generateSuccessResponse('Abrí el formulario de cambio de cama.', [], $actions);
    }

    private function handleIndicarMedicacion($message, $parameters, $context, $userId)
    {
        $idInternacion = $this->resolveInternacionId($parameters);
        if (!$idInternacion) {
            $context = $this->updateContext($userId, $context, 'internacion_indicar_medicacion', $parameters);
            $context = ConversationContext::setAwaitingInput($context, 'id_internacion');
            $resp = $this->generateMissingParamsResponse(['id_internacion'], 'internacion_indicar_medicacion');
            $resp['context_update'] = $context;
            return $resp;
        }

        $context = $this->updateContext($userId, $context, 'internacion_indicar_medicacion', array_merge($parameters, ['id_internacion' => $idInternacion]));
        $context = ConversationContext::markCompleted($context);

        $actions = $this->actionsFromAgent(
            "indicar medicación internación " . (int)$idInternacion .
            (!empty($parameters['medicamento']) ? (" " . $parameters['medicamento']) : ''),
            $userId
        );
        if (empty($actions)) {
            $actions = [[
                'type' => 'open_route',
                'title' => 'Indicar medicación',
                'route' => $this->route('/internacion-medicamento/create'),
                'params' => ['id' => (int)$idInternacion],
            ]];
        }

        return $this->generateSuccessResponse('Abrí el formulario para indicar medicación.', [], $actions);
    }

    private function handleAgregarDiagnostico($message, $parameters, $context, $userId)
    {
        $idInternacion = $this->resolveInternacionId($parameters);
        if (!$idInternacion) {
            $context = $this->updateContext($userId, $context, 'internacion_agregar_diagnostico', $parameters);
            $context = ConversationContext::setAwaitingInput($context, 'id_internacion');
            $resp = $this->generateMissingParamsResponse(['id_internacion'], 'internacion_agregar_diagnostico');
            $resp['context_update'] = $context;
            return $resp;
        }

        $context = $this->updateContext($userId, $context, 'internacion_agregar_diagnostico', array_merge($parameters, ['id_internacion' => $idInternacion]));
        $context = ConversationContext::markCompleted($context);

        $actions = $this->actionsFromAgent(
            "agregar diagnóstico internación " . (int)$idInternacion .
            (!empty($parameters['diagnostico']) ? (" " . $parameters['diagnostico']) : ''),
            $userId
        );
        if (empty($actions)) {
            $actions = [[
                'type' => 'open_route',
                'title' => 'Agregar diagnóstico',
                'route' => $this->route('/internacion-diagnostico/create'),
                'params' => ['id' => (int)$idInternacion],
            ]];
        }

        return $this->generateSuccessResponse('Abrí el formulario para agregar diagnóstico.', [], $actions);
    }

    private function handleAgregarPractica($message, $parameters, $context, $userId)
    {
        $idInternacion = $this->resolveInternacionId($parameters);
        if (!$idInternacion) {
            $context = $this->updateContext($userId, $context, 'internacion_agregar_practica', $parameters);
            $context = ConversationContext::setAwaitingInput($context, 'id_internacion');
            $resp = $this->generateMissingParamsResponse(['id_internacion'], 'internacion_agregar_practica');
            $resp['context_update'] = $context;
            return $resp;
        }

        $context = $this->updateContext($userId, $context, 'internacion_agregar_practica', array_merge($parameters, ['id_internacion' => $idInternacion]));
        $context = ConversationContext::markCompleted($context);

        $actions = $this->actionsFromAgent(
            "agregar práctica internación " . (int)$idInternacion .
            (!empty($parameters['practica']) ? (" " . $parameters['practica']) : ''),
            $userId
        );
        if (empty($actions)) {
            $actions = [[
                'type' => 'open_route',
                'title' => 'Agregar práctica',
                'route' => $this->route('/internacion-practica/create'),
                'params' => ['id' => (int)$idInternacion],
            ]];
        }

        return $this->generateSuccessResponse('Abrí el formulario para agregar práctica.', [], $actions);
    }

    private function handleEstado($message, $parameters, $context, $userId)
    {
        $internacion = null;
        if (!empty($parameters['id_internacion'])) {
            $internacion = SegNivelInternacion::findOne((int)$parameters['id_internacion']);
        } elseif (!empty($parameters['id_persona'])) {
            $internacion = SegNivelInternacion::personaInternada((int)$parameters['id_persona']);
        }

        $context = $this->updateContext($userId, $context, 'estado_internacion', $parameters);
        $context = ConversationContext::markCompleted($context);

        if (!$internacion) {
            return $this->generateSuccessResponse('No encontré una internación activa para ese paciente.', [], []);
        }

        $data = [
            'id_internacion' => (int)$internacion->id,
            'id_persona' => (int)$internacion->id_persona,
            'id_cama' => (int)$internacion->id_cama,
            'fecha_inicio' => $internacion->fecha_inicio,
        ];

        $actions = $this->actionsFromAgent("ver internación " . (int)$internacion->id, $userId);
        if (empty($actions)) {
            $actions = [[
                'type' => 'open_route',
                'title' => 'Ver internación',
                'route' => $this->route('/internacion/view'),
                'params' => ['id' => (int)$internacion->id],
            ]];
        }

        return $this->generateSuccessResponse('Internación activa encontrada.', $data, $actions);
    }

    /**
     * A2: pedir acciones al UniversalQueryAgent.
     * Devuelve acciones ya convertidas al contrato `open_route` cuando sea posible.
     */
    private function actionsFromAgent(string $query, $userId): array
    {
        try {
            $result = UniversalQueryAgent::processQuery($query, $userId);
            $actions = $result['data']['actions'] ?? $result['actions'] ?? [];
            return $this->adaptAgentActions($actions);
        } catch (\Exception $e) {
            Yii::warning("InternacionHandler: UniversalQueryAgent falló: " . $e->getMessage(), 'intent-handler');
            return [];
        }
    }

    /**
     * Convertir acciones del agente al contrato `open_route`.
     * Si no trae route, se devuelve vacío para que aplique fallback.
     */
    private function adaptAgentActions(array $actions): array
    {
        if (empty($actions)) {
            return [];
        }

        $adapted = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $route = $action['route'] ?? null;
            if (empty($route)) {
                continue;
            }

            $adapted[] = [
                'type' => 'open_route',
                'title' => $action['display_name'] ?? $action['action_id'] ?? 'Abrir',
                'route' => $route,
                'params' => [],
                'action_id' => $action['action_id'] ?? null,
            ];
        }

        return $adapted;
    }

    private function resolveInternacionId(array $parameters): ?int
    {
        if (!empty($parameters['id_internacion'])) {
            return (int)$parameters['id_internacion'];
        }

        if (!empty($parameters['id_persona'])) {
            $idPersona = (int)$parameters['id_persona'];
        $idEfector = Yii::$app->user->getIdEfector();
            if ($idEfector) {
                $id = SegNivelInternacion::personaInternadaEnEfector($idPersona, $idEfector);
                if ($id) {
                    return (int)$id;
                }
            }
            $internacion = SegNivelInternacion::personaInternada($idPersona);
            return $internacion ? (int)$internacion->id : null;
        }

        return null;
    }

    private function route(string $path): string
    {
        $base = Yii::$app->params['path'] ?? '';
        $base = '/' . trim((string)$base, '/');
        $base = $base === '/' ? '' : $base;
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

