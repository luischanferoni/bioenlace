<?php

namespace common\components\Shared\Infrastructure\Http;

/**
 * Cuerpo `multipart/form-data` con un archivo en memoria.
 *
 * Existe para que un ACL pueda subir bytes (audio, imágenes) sin escribirlos antes a un
 * archivo temporal: el transporte arma el multipart.
 */
final class MultipartBody
{
    /**
     * @param string $field nombre del campo del archivo (p. ej. `file`)
     * @param string $content bytes del archivo
     * @param string $fileName nombre que verá el proveedor
     * @param string $mimeType tipo MIME del archivo
     * @param array<string, scalar> $fields campos de formulario adicionales
     */
    public function __construct(
        public readonly string $field,
        public readonly string $content,
        public readonly string $fileName,
        public readonly string $mimeType = 'application/octet-stream',
        public readonly array $fields = []
    ) {
    }
}
