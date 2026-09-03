<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogEntry;

/**
 * Decisión de routing post catálogo inteligente.
 */
final class SmartCatalogRoutingDecision
{
    /**
     * @param list<string> $intentIds
     */
    public function __construct(
        public readonly string $routingResult,
        public readonly string $legacyUserGoal,
        public readonly array $intentIds,
        public readonly string $responseText,
        public readonly string $articleTopic,
        public readonly ?SmartCatalogEntry $catalogEntry = null,
    ) {
    }

    public function primaryIntentId(): string
    {
        return $this->intentIds[0] ?? '';
    }

    public function isMatch100(): bool
    {
        return $this->routingResult === 'clara';
    }

    public function shouldRouteIntentDirectly(): bool
    {
        return $this->isMatch100() && count($this->intentIds) === 1;
    }

    public function isDirectArticle(): bool
    {
        return $this->isMatch100() && $this->articleTopic !== '';
    }

    public function isDirectTemplate(): bool
    {
        return $this->isMatch100()
            && $this->articleTopic === ''
            && $this->intentIds === []
            && trim($this->responseText) !== '';
    }

    public function isFueraDeHis(): bool
    {
        return $this->routingResult === 'fuera_de_his';
    }

    public function isDudosa(): bool
    {
        return $this->routingResult === 'dudosa';
    }

    public function isIncompletas(): bool
    {
        return $this->routingResult === 'incompletas';
    }
}
