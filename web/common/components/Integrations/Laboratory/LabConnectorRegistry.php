<?php

namespace common\components\Integrations\Laboratory;

use common\components\Integrations\Laboratory\Connector\SianlabsFhirConnector;
use common\components\Integrations\Laboratory\Contract\FhirLabResultsConnector;
use common\components\Integrations\Laboratory\Exception\LaboratoryConnectorException;
use Yii;
use yii\base\InvalidConfigException;

final class LabConnectorRegistry
{
    /** @var array<string, FhirLabResultsConnector> */
    private static array $instances = [];

    public static function get(?string $key = null): FhirLabResultsConnector
    {
        $config = Yii::$app->params['laboratoryConnectors'] ?? null;
        if (!is_array($config)) {
            throw new LaboratoryConnectorException('params[laboratoryConnectors] no configurado.');
        }

        $key = $key ?? ($config['default'] ?? null);
        if ($key === null || $key === '') {
            throw new LaboratoryConnectorException('laboratoryConnectors.default no definido.');
        }

        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $connectors = $config['connectors'] ?? [];
        if (!isset($connectors[$key]) || !is_array($connectors[$key])) {
            throw new LaboratoryConnectorException("Conector de laboratorio desconocido: {$key}");
        }

        $def = $connectors[$key];
        $class = $def['class'] ?? SianlabsFhirConnector::class;
        if (!is_a($class, FhirLabResultsConnector::class, true)) {
            throw new InvalidConfigException("Clase de conector inválida: {$class}");
        }

        /** @var FhirLabResultsConnector $instance */
        $instance = Yii::createObject(array_merge(['connectorKey' => $key], $def));
        self::$instances[$key] = $instance;

        return $instance;
    }

    /** @return string[] */
    public static function configuredKeys(): array
    {
        $config = Yii::$app->params['laboratoryConnectors'] ?? [];
        $connectors = $config['connectors'] ?? [];

        return array_keys($connectors);
    }
}
