<?php
declare(strict_types=1);

/**
 * StorageDriver — contrato común de almacenamiento de archivos.
 *
 * La clave (`$key`) es SIEMPRE relativa a la raíz de uploads, con el prefijo de módulo:
 *   'creditos/cred1_x.pdf', 'pagos/2026/09/pago_x.pdf', 'convenios/conv_a.jpg', …
 * Es la MISMA ruta relativa que se guarda en la BD, así el refactor no toca columnas.
 *
 * Implementaciones:
 *   - LocalDriver: comportamiento actual (directorio + readfile + cabeceras).
 *   - R2Driver: usa utils/vendor/r2min (bucket privado) o URL pública (bucket público).
 *
 * `serve()` emite las cabeceras (Content-Type/Length/Cache-Control/Disposition) y el cuerpo.
 * `url()` devuelve una URL pública (si el módulo es público) o null (si privado).
 */
interface StorageDriver
{
    /** Guarda el contenido de $streamOrPath (stream en 'rb' o ruta/string) bajo $key. */
    public function put(string $key, $streamOrPath, array $opts = []): void;

    /** Emite cabeceras + cuerpo del objeto $key (proxy autenticado para privados). */
    public function serve(string $key, array $opts = []): void;

    /** URL pública del objeto (solo para módulos públicos); null si no aplica. */
    public function url(string $key, array $opts = []): ?string;

    public function exists(string $key): bool;

    public function delete(string $key): void;
}