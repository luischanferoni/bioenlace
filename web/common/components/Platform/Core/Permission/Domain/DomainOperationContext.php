<?php

namespace common\components\Platform\Core\Permission\Domain;

/**
 * Contexto operativo para evaluar políticas de dominio (sin revalidar identidad JWT).
 */
final class DomainOperationContext
{
    /** @var array<string, mixed> */
    public array $params;

    public function __construct(
        public int $userId,
        public int $idPersona,
        public ?int $idEfector,
        public bool $isSuperadmin,
        array $params = []
    ) {
        $this->params = $params;
    }

    /**
     * @param array<string, mixed> $params parámetros del request (id_efector, id_persona, …)
     */
    public static function fromApplication(array $params = []): self
    {
        $user = \Yii::$app->user;

        return new self(
            (int) ($user->id ?? 0),
            (int) $user->getIdPersona(),
            self::resolveIdEfector($params),
            (bool) ($user->isSuperadmin ?? false),
            $params
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function resolveIdEfector(array $params): ?int
    {
        foreach (['id_efector', 'idEfector'] as $key) {
            if (isset($params[$key]) && (int) $params[$key] > 0) {
                return (int) $params[$key];
            }
        }
        $fromSession = (int) \Yii::$app->user->getIdEfector();

        return $fromSession > 0 ? $fromSession : null;
    }
}
