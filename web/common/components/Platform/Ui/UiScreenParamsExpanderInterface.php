<?php

namespace common\components\Platform\Ui;

/**
 * Expande query/post params antes de renderizar plantillas UI JSON.
 */
interface UiScreenParamsExpanderInterface
{
    public static function providerKey(): string;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function expand(string $entity, string $action, array $params): array;
}
