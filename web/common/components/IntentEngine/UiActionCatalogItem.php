<?php

namespace common\components\IntentEngine;

/**
 * DTO de una UI sugerible para el asistente.
 */
final class UiActionCatalogItem
{
    public string $action_id;
    public string $display_name;
    public string $description;
    public ?string $entity;
    public string $route;

    /** @var string[] */
    public array $keywords;

    /** @var array<string, mixed> */
    public array $parameters;

    /** @var array<string, mixed>|null Instrucción explícita para abrir UI (web/mobile). */
    public ?array $client_open = null;

    /** @var string|null Etiqueta de interacción (ej. ui_asistente_json|ui_asistente_native). */
    public ?string $client_interaction = null;

    /**
     * @param string[] $keywords
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        string $action_id,
        string $display_name,
        string $description,
        ?string $entity,
        string $route,
        array $keywords,
        array $parameters,
        ?array $client_open = null,
        ?string $client_interaction = null
    ) {
        $this->action_id = $action_id;
        $this->display_name = $display_name;
        $this->description = $description;
        $this->entity = $entity;
        $this->route = $route;
        $this->keywords = $keywords;
        $this->parameters = $parameters;
        $this->client_open = $client_open;
        $this->client_interaction = $client_interaction;
    }

    /**
     * @return array{route:string,action_id:string,title:string,description:string,keywords:string[],entity?:string}
     */
    public function toPromptArray(): array
    {
        $row = [
            'route' => $this->route,
            'action_id' => $this->action_id,
            'title' => $this->display_name,
            'description' => $this->description,
            'keywords' => $this->keywords,
        ];
        if ($this->entity !== null && $this->entity !== '') {
            $row['entity'] = $this->entity;
        }

        return $row;
    }
}

