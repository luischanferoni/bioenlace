<?php

namespace common\components\Platform\Assistant\Context;

/**
 * Plan de aspectos a cargar tras preprocess.
 */
final class AssistantContextLoadPlan
{
    /** @var list<string> */
    public array $aspectKeys = [];

    public AssistantContextAnchorBag $anchors;

    /**
     * @param list<string> $aspectKeys
     */
    public function __construct(array $aspectKeys, AssistantContextAnchorBag $anchors)
    {
        $this->aspectKeys = array_values(array_unique($aspectKeys));
        $this->anchors = $anchors;
    }

    public function isEmpty(): bool
    {
        return $this->aspectKeys === [];
    }
}
