<?php

namespace common\components\Domain\Integrations\ClinicalHistory;

use common\components\Domain\Integrations\ClinicalHistory\Connector\HttpNationalClinicalHistoryConnector;
use common\components\Domain\Integrations\ClinicalHistory\Connector\NullClinicalHistoryExchangeConnector;
use common\components\Domain\Integrations\ClinicalHistory\Contract\ClinicalHistoryExchangeConnector;
use common\components\Domain\Integrations\ClinicalHistory\Exception\ClinicalHistoryExchangeException;
use Yii;
use yii\base\InvalidConfigException;

final class ClinicalHistoryExchangeRegistry
{
    /** @var array<string, ClinicalHistoryExchangeConnector> */
    private static array $instances = [];

    public static function get(?string $key = null): ClinicalHistoryExchangeConnector
    {
        $config = Yii::$app->params['clinicalHistoryExchange'] ?? null;
        if (!is_array($config)) {
            throw new ClinicalHistoryExchangeException('params[clinicalHistoryExchange] no configurado.');
        }

        $key = $key ?? ($config['default'] ?? 'null');
        if ($key === null || $key === '') {
            throw new ClinicalHistoryExchangeException('clinicalHistoryExchange.default no definido.');
        }

        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $connectors = $config['connectors'] ?? [];
        if (!isset($connectors[$key]) || !is_array($connectors[$key])) {
            throw new ClinicalHistoryExchangeException("Conector de historia clínica desconocido: {$key}");
        }

        $def = $connectors[$key];
        $class = $def['class'] ?? NullClinicalHistoryExchangeConnector::class;
        if (!is_a($class, ClinicalHistoryExchangeConnector::class, true)) {
            throw new InvalidConfigException("Clase de conector inválida: {$class}");
        }

        /** @var ClinicalHistoryExchangeConnector $instance */
        $instance = Yii::createObject(array_merge(['connectorKey' => $key], $def));
        self::$instances[$key] = $instance;

        return $instance;
    }

    /** @return array<string, mixed> */
    public static function config(): array
    {
        $config = Yii::$app->params['clinicalHistoryExchange'] ?? [];

        return is_array($config) ? $config : [];
    }

    public static function isMasterEnabled(): bool
    {
        return (bool) (self::config()['enabled'] ?? false);
    }
}
