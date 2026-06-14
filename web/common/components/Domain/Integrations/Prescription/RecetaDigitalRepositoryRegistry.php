<?php

namespace common\components\Domain\Integrations\Prescription;

use common\components\Domain\Integrations\Prescription\Connector\HttpRecetaDigitalRepositoryConnector;
use common\components\Domain\Integrations\Prescription\Connector\NullRecetaDigitalRepositoryConnector;
use common\components\Domain\Integrations\Prescription\Contract\RecetaDigitalRepositoryConnector;
use common\components\Domain\Integrations\Prescription\Exception\RecetaDigitalRepositoryException;
use Yii;
use yii\base\InvalidConfigException;

final class RecetaDigitalRepositoryRegistry
{
    /** @var array<string, RecetaDigitalRepositoryConnector> */
    private static array $instances = [];

    public static function get(?string $key = null): RecetaDigitalRepositoryConnector
    {
        $config = Yii::$app->params['recetaDigitalRepository'] ?? null;
        if (!is_array($config)) {
            throw new RecetaDigitalRepositoryException('params[recetaDigitalRepository] no configurado.');
        }

        $key = $key ?? ($config['default'] ?? 'null');
        if ($key === null || $key === '') {
            throw new RecetaDigitalRepositoryException('recetaDigitalRepository.default no definido.');
        }

        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $connectors = $config['connectors'] ?? [];
        if (!isset($connectors[$key]) || !is_array($connectors[$key])) {
            throw new RecetaDigitalRepositoryException("Conector de receta digital desconocido: {$key}");
        }

        $def = $connectors[$key];
        $class = $def['class'] ?? NullRecetaDigitalRepositoryConnector::class;
        if (!is_a($class, RecetaDigitalRepositoryConnector::class, true)) {
            throw new InvalidConfigException("Clase de conector inválida: {$class}");
        }

        /** @var RecetaDigitalRepositoryConnector $instance */
        $instance = Yii::createObject(array_merge(['connectorKey' => $key], $def));
        self::$instances[$key] = $instance;

        return $instance;
    }

    /** @return string[] */
    public static function configuredKeys(): array
    {
        $config = Yii::$app->params['recetaDigitalRepository'] ?? [];
        $connectors = $config['connectors'] ?? [];

        return array_keys($connectors);
    }
}
