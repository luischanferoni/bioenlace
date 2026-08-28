<?php

namespace common\components\Platform\Assistant\Context;

interface AssistantContextAspectLoaderInterface
{
    public function aspectKey(): string;

    /**
     * @return array<string, mixed> Payload HIS JSON-serializable
     */
    public function load(AssistantContextLoadContext $ctx): array;
}
