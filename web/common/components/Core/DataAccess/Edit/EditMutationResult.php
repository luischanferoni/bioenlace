<?php

namespace common\components\Core\DataAccess\Edit;

/**
 * Resultado de {@see MutationExecutor}.
 */
final class EditMutationResult
{
    /**
     * @param list<array{field: string, label: string, before: string, after: string}> $appliedChanges
     * @param list<array{aspect_id: string, action_id: string, params: array<string, string>}> $openUiActions
     * @param array<string, int|string> $subjectContext
     */
    public function __construct(
        public array $appliedChanges,
        public array $openUiActions,
        public array $subjectContext
    ) {
    }

    public function hasScalarChanges(): bool
    {
        return $this->appliedChanges !== [];
    }

    public function hasOpenUiActions(): bool
    {
        return $this->openUiActions !== [];
    }
}
