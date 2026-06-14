<?php

namespace common\components\Core\DataAccess;

use common\components\Organization\DataAccess\Scope\EfectorSesionScopeChecker;
use common\components\Organization\DataAccess\Scope\EfectorSesionViaPesScopeChecker;
use common\components\Person\DataAccess\Scope\PermitirParaSiMismoScopeChecker;

/**
 * Registro estable de scope checkers (IDs declarados en metadata YAML).
 */
final class ScopeCheckerRegistry
{
    /** @var array<string, ScopeCheckerInterface> */
    private static array $instances = [];

    /** @var array<string, class-string<ScopeCheckerInterface>> */
    private const HANDLERS = [
        'efector_sesion' => EfectorSesionScopeChecker::class,
        'efector_sesion_via_pes' => EfectorSesionViaPesScopeChecker::class,
        'permitir_para_si_mismo' => PermitirParaSiMismoScopeChecker::class,
    ];

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
        if (!isset(self::HANDLERS[$checkerId])) {
            throw new \InvalidArgumentException('scope_checker desconocido: ' . $checkerId);
        }

        $class = self::HANDLERS[$checkerId];

        return new $class();
    }

    /** @return list<string> */
    public static function knownIds(): array
    {
        return array_keys(self::HANDLERS);
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
