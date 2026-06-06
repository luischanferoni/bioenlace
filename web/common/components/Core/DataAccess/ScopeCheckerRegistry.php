<?php

namespace common\components\Core\DataAccess;

use common\components\Core\DataAccess\Scope\EfectorSesionScopeChecker;
use common\components\Core\DataAccess\Scope\PermitirParaSiMismoScopeChecker;

/**
 * Registro estable de scope checkers (IDs declarados en metadata YAML).
 */
final class ScopeCheckerRegistry
{
    /** @var array<string, ScopeCheckerInterface> */
    private static array $instances = [];

    public static function get(string $checkerId): ScopeCheckerInterface
    {
        $checkerId = trim($checkerId);
        if ($checkerId === '') {
            throw new \InvalidArgumentException('scope_checker vacío.');
        }

        if (!isset(self::$instances[$checkerId])) {
            self::$instances[$checkerId] = self::build($checkerId);
        }

        return self::$instances[$checkerId];
    }

    private static function build(string $checkerId): ScopeCheckerInterface
    {
        switch ($checkerId) {
            case 'efector_sesion':
            case 'efector_sesion_via_pes':
                return new EfectorSesionScopeChecker();
            case 'permitir_para_si_mismo':
                return new PermitirParaSiMismoScopeChecker();
            default:
                throw new \InvalidArgumentException('scope_checker desconocido: ' . $checkerId);
        }
    }

    /** @return list<string> */
    public static function knownIds(): array
    {
        return ['efector_sesion', 'efector_sesion_via_pes', 'permitir_para_si_mismo'];
    }

    /** @return array<string, string> */
    public static function optionsForForm(): array
    {
        $out = ['' => '(sin scope checker)'];
        foreach (self::knownIds() as $id) {
            $out[$id] = $id;
        }

        return $out;
    }
}
