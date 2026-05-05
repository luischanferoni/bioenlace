<?php

namespace console\controllers;

use common\components\Assistant\Graph\GraphFlowManifestCompiler;
use common\components\Assistant\Graph\GraphRegistry;
use yii\console\Controller;
use yii\helpers\Json;

/**
 * Harness para probar el compilador de grafo -> flow_manifest.
 *
 * Ejemplos:
 *   php yii assistant-graph/test --message="quiero sacar turno"
 *   php yii assistant-graph/test --message="qué servicios hay"
 *   php yii assistant-graph/test --operation="turnos.crear-como-paciente" --draft='{"id_servicio_asignado":"6"}'
 *   php yii assistant-graph/test --resolver="Servicio.elegir_para_turnos"
 */
final class AssistantGraphController extends Controller
{
    public string $message = '';
    public string $operation = '';
    public string $resolver = '';
    public string $draft = '';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['message', 'operation', 'resolver', 'draft']);
    }

    public function actionTest(): int
    {
        try {
            $reg = GraphRegistry::loadTurnos();

            $draftArr = [];
            if (trim($this->draft) !== '') {
                $decoded = Json::decode($this->draft);
                if (!is_array($decoded)) {
                    $this->stderr("--draft debe ser un JSON object\n");
                    return self::EXIT_CODE_ERROR;
                }
                /** @var array<string, mixed> $draftArr */
                $draftArr = $decoded;
            }

            $resolverExplicit = trim($this->resolver);
            $opExplicit = trim($this->operation);

            $intent = null;
            if ($resolverExplicit !== '') {
                $intent = [
                    'type' => 'browse',
                    'operation_id' => null,
                    'resolver_id' => $resolverExplicit,
                    'matched_keyword' => null,
                ];
            } elseif ($opExplicit !== '') {
                $intent = [
                    'type' => 'operation',
                    'operation_id' => $opExplicit,
                    'resolver_id' => null,
                    'matched_keyword' => null,
                ];
            } else {
                $intent = $reg->detectIntent($this->message);
            }

            if (($intent['type'] ?? '') === 'browse' && !empty($intent['resolver_id'])) {
                $out = GraphFlowManifestCompiler::compileBrowse($reg, (string) $intent['resolver_id'], $draftArr);
                $payload = [
                    'intent' => $intent,
                    'flow_manifest' => $out['flow_manifest'],
                    'debug' => $out['debug'],
                ];
                $this->stdout(Json::encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
                return self::EXIT_CODE_NORMAL;
            }

            $opId = (string) ($intent['operation_id'] ?? '');
            if ($opId === '') {
                $this->stderr("No se detectó intención. Usá --operation, --resolver o un --message más claro.\n");
                return self::EXIT_CODE_ERROR;
            }

            $out = GraphFlowManifestCompiler::compileOperation($reg, $opId, $draftArr);
            $payload = [
                'intent' => $intent,
                'flow_manifest' => $out['flow_manifest'],
                'debug' => $out['debug'],
            ];
            $this->stdout(Json::encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            return self::EXIT_CODE_NORMAL;
        } catch (\Throwable $e) {
            $this->stderr('Error: ' . $e->getMessage() . "\n");
            return self::EXIT_CODE_ERROR;
        }
    }
}
