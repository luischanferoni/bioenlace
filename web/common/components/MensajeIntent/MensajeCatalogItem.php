<?php

namespace common\components\MensajeIntent;

/**
 * Formato mínimo de un destino posible para el mensaje del usuario.
 * Se usa en reglas, prompt de IA y (si aplica) enrutado a handler o acción.
 */
final class MensajeCatalogItem
{
    /** Ruta Yii asociada; vacío si el ítem es solo conversación (sin ruta web). */
    public string $route;

    /** Identificador estable: conv:{categoria}.{intent} o action_id descubierto. */
    public string $action_id;

    public string $title;

    public string $description;

    /** @var string[] */
    public array $keywords;

    /** @var string[] Patrones regex (p. ej. desde intent-categories) para fase de reglas. */
    public array $patterns;

    /** Si está definido, se enruta al IntentHandlerRegistry. */
    public ?string $category = null;

    public ?string $intent = null;

    /** critical|high|medium|low — mismo criterio que intent-categories. */
    public string $priority = 'low';

    /**
     * @param string[] $keywords
     * @param string[] $patterns
     */
    public function __construct(
        string $route,
        string $action_id,
        string $title,
        string $description,
        array $keywords = [],
        array $patterns = [],
        ?string $category = null,
        ?string $intent = null,
        string $priority = 'low'
    ) {
        $this->route = $route;
        $this->action_id = $action_id;
        $this->title = $title;
        $this->description = $description;
        $this->keywords = $keywords;
        $this->patterns = $patterns;
        $this->category = $category;
        $this->intent = $intent;
        $this->priority = $priority;
    }

    public function isConversation(): bool
    {
        return $this->category !== null && $this->intent !== null;
    }

    /**
     * Objeto enviado al modelo (sin patterns ni category/intent redundantes en exceso).
     * @return array{route:string,action_id:string,title:string,description:string,keywords:string[]}
     */
    public function toPromptArray(): array
    {
        return [
            'route' => $this->route,
            'action_id' => $this->action_id,
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
        ];
    }
}
