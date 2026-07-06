<?php

namespace common\components\Domain\Integrations\Scheduling;

use common\components\Domain\Integrations\Scheduling\Connector\MsalNisFhirSchedulingConnector;
use common\components\Domain\Integrations\Scheduling\Contract\FhirSchedulingInboundConnector;
use common\components\Domain\Integrations\Scheduling\Exception\FhirSchedulingConnectorException;
use Yii;
use yii\base\InvalidConfigException;

final class FhirSchedulingConnectorRegistry
{
    /** @var array<string, FhirSchedulingInboundConnector> */
    private static array $instances = [];

    public static function get(?string $key = null): FhirSchedulingInboundConnector
    {
        $config = Yii::$app->params['fhirSchedulingInbound'] ?? null;
        if (!is_array($config)) {
            throw new FhirSchedulingConnectorException('params[fhirSchedulingInbound] no configurado.');
        }

        $key = $key ?? ($config['default'] ?? null);
        if ($key === null || $key === '') {
            throw new FhirSchedulingConnectorException('fhirSchedulingInbound.default no definido.');
        }

        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $connectors = $config['connectors'] ?? [];
        if (!isset($connectors[$key]) || !is_array($connectors[$key])) {
            throw new FhirSchedulingConnectorException("Conector FHIR scheduling desconocido: {$key}");
        }

        $def = $connectors[$key];
        $class = $def['class'] ?? MsalNisFhirSchedulingConnector::class;
        if (!is_a($class, FhirSchedulingInboundConnector::class, true)) {
            throw new InvalidConfigException("Clase de conector inválida: {$class}");
        }

        /** @var FhirSchedulingInboundConnector $instance */
        $instance = Yii::createObject(array_merge(['connectorKey' => $key], $def));
        self::$instances[$key] = $instance;

        return $instance;
    }
}
