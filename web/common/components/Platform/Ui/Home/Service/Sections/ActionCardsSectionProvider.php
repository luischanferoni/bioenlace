<?php

namespace common\components\Platform\Ui\Home\Service\Sections;

use Yii;
use common\components\Platform\Core\Service\Actions\CommonActionsService;

final class ActionCardsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return ['categories' => [], 'actions' => []];
        }

        return CommonActionsService::getFormattedForUser($userId);
    }
}
