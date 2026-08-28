<?php

namespace common\components\Platform\Assistant\Context;

final class AssistantContextAssemblyResult
{
    /** @var list<array{aspect: string, chars: int}> */
    public array $applied = [];

    public string $promptSection = '';

    /** @var array<string, mixed> */
    public array $scopeApplied = [];

    public function isEmpty(): bool
    {
        return trim($this->promptSection) === '';
    }
}
