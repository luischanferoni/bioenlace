<?php

namespace common\components\Platform\Assistant\Context;

/**
 * Anclas resueltas para filtros de loaders (IDs, no texto NL).
 */
final class AssistantContextAnchorBag
{
    public int $subjectPersonaId = 0;
    public int $appointmentId = 0;
    public int $siteId = 0;
    public int $serviceId = 0;
    public int $pesId = 0;

    /** @var array<string, string> aspect_key => resolved_from */
    public array $resolvedFrom = [];

    public function withResolvedFrom(string $key, string $source): self
    {
        $this->resolvedFrom[$key] = $source;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toScopeArray(): array
    {
        $out = [];
        if ($this->subjectPersonaId > 0) {
            $out['subject_persona_id'] = $this->subjectPersonaId;
        }
        if ($this->appointmentId > 0) {
            $out['appointment_id'] = $this->appointmentId;
        }
        if ($this->siteId > 0) {
            $out['site_id'] = $this->siteId;
        }
        if ($this->serviceId > 0) {
            $out['service_id'] = $this->serviceId;
        }
        if ($this->pesId > 0) {
            $out['pes_id'] = $this->pesId;
        }
        if ($this->resolvedFrom !== []) {
            $out['resolved_from'] = $this->resolvedFrom;
        }

        return $out;
    }
}
