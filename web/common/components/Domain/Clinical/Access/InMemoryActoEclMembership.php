<?php

namespace common\components\Domain\Clinical\Access;

/**
 * Membership ECL predefinida para tests (sin Snowstorm).
 *
 * Clave: "{system}|{code}" → lista de ECLs (exact match de string) que incluyen el acto.
 */
final class InMemoryActoEclMembership implements ActoEclMembershipInterface
{
    /** @var array<string, list<string>> */
    private array $membership;

    /**
     * @param array<string, list<string>> $membership map system|code → list of act_ecl strings
     */
    public function __construct(array $membership = [])
    {
        $this->membership = $membership;
    }

    public function matches(string $code, string $system, string $ecl): bool
    {
        $key = trim($system) . '|' . trim($code);
        $ecl = trim($ecl);
        if ($ecl === '' || !isset($this->membership[$key])) {
            return false;
        }

        return in_array($ecl, $this->membership[$key], true);
    }
}
