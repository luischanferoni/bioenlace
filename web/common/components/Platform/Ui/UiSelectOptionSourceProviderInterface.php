<?php

namespace common\components\Platform\Ui;

/**
 * Resuelve opciones de un `option_config.source` declarado en UI JSON.
 */
interface UiSelectOptionSourceProviderInterface
{
    public static function providerKey(): string;

    /**
     * @param mixed $filter
     * @param array<string, mixed> $params
     * @param array<string, mixed> $optionConfig
     * @return list<array<string, mixed>>
     */
    public static function resolve(string $sourceKey, $filter, array $params, array $optionConfig): array;
}
