<?php

namespace common\components\Platform\Core\Http\Contract;

/**
 * Error de negocio con código del catálogo. Los servicios lanzan esto en lugar de redactar texto.
 */
class ApiException extends \RuntimeException
{
    private string $errorCode;

    /** @var array<string, scalar> */
    private array $params;

    /** @var array<string, list<string>> */
    private array $fieldErrors;

    /** @var array<string, mixed> */
    private array $meta;

    /**
     * @param array<string, scalar> $params marcadores del mensaje del catálogo
     * @param array<string, list<string>> $fieldErrors errores por campo (mensajes ya redactados por el backend)
     * @param array<string, mixed> $meta contexto para el cliente (p. ej. estado de la captura)
     */
    public function __construct(
        string $errorCode,
        array $params = [],
        array $fieldErrors = [],
        ?\Throwable $previous = null,
        array $meta = []
    ) {
        parent::__construct($errorCode, 0, $previous);
        $this->errorCode = $errorCode;
        $this->params = $params;
        $this->fieldErrors = $fieldErrors;
        $this->meta = $meta;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, scalar> */
    public function params(): array
    {
        return $this->params;
    }

    /** @return array<string, list<string>> */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
