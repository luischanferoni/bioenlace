<?php

namespace common\components\Platform\Assistant\Context;

/**
 * Contexto de ejecución para loaders de aspectos.
 */
final class AssistantContextLoadContext
{
    public int $userId = 0;
    public string $channel = '';

    public AssistantContextAnchorBag $anchors;

    /** @var list<array{span: string, category: string, synonyms: list<string>}> */
    public array $extractions = [];

    public function __construct(int $userId, string $channel, AssistantContextAnchorBag $anchors, array $extractions = [])
    {
        $this->userId = $userId;
        $this->channel = trim($channel);
        $this->anchors = $anchors;
        $this->extractions = $extractions;
    }
}
