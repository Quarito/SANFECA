<?php

namespace App\Services\Coordinacion;

/**
 * Identidad completa de una versión documental: estado de carga + ruta + SHA-256 + tamaño.
 * Se calcula SIEMPRE en el servidor a partir del archivo real; el navegador solo devuelve el token.
 */
final class VersionDocumento
{
    public const CARGADO = 'cargado';
    public const NO_CARGADO = 'no_cargado';
    public const NO_LOCALIZADO = 'no_localizado';

    public function __construct(
        public readonly string $estado,
        public readonly ?string $ruta,
        public readonly ?string $sha256,
        public readonly ?int $bytes,
    ) {
    }

    public function token(): string
    {
        return hash('sha256', implode("\x1f", [$this->estado, $this->ruta ?? '', $this->sha256 ?? '', $this->bytes === null ? '' : (string) $this->bytes]));
    }

    public function igualA(self $otra): bool
    {
        return hash_equals($this->token(), $otra->token());
    }

    public function cargado(): bool
    {
        return $this->estado === self::CARGADO;
    }

    /** Datos visibles: nombre de archivo (sin la carpeta interna), huella abreviada y tamaño. */
    public function publica(): array
    {
        return [
            'estado' => $this->estado,
            'archivo' => $this->ruta !== null ? basename($this->ruta) : null,
            'sha256_corto' => $this->sha256 !== null ? substr($this->sha256, 0, 12) : null,
            'bytes' => $this->bytes,
            'token' => $this->token(),
        ];
    }
}
