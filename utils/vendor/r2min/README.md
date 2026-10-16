# r2min — mini-cliente S3 / Cloudflare R2 (sin dependencias)

Cliente ligero de R2 para `multi_app_v2`. Implementa lo estrictamente necesario para que
la capa `core/Storage` guarde y sirva objetos: `putObject`, `getObject`, `exists` y
`deleteObject`, autenticando con **AWS SigV4** contra el endpoint de la cuenta.

## Por qué es ligero
Coherente con el resto de `utils/vendor/` (`tcpdf`, `simplexlsx`): **esta versión no usa
Composer ni aws-sdk-php** (decisión del líder). Un `composer.json`/`vendor/` grande no aporta
nada aquí porque solo necesitamos 4 operaciones de objeto contra un bucket R2. El diseño
aisla todo en `core/Storage/R2Driver`; si a futuro se quiere CDN o presigned URLs, se cambia
solo ese driver, no el cliente.

## Uso
```php
require_once __DIR__ . '/../../core/env.php'; // carga .env (opcional si ya está)
require_once __DIR__ . '/R2Client.php';

$r2 = new R2Client(
    env('R2_ENDPOINT') ?: ('https://' . env('R2_ACCOUNT_ID') . '.r2.cloudflarestorage.com'),
    env('R2_ACCESS_KEY_ID'),
    env('R2_SECRET_ACCESS_KEY'),
);

// subir un archivo (string o stream en 'rb')
$r2->putObject('mi-bucket', 'carpeta/doc.pdf', fopen($tmp, 'rb'), [
    'Content-Type' => 'application/pdf',
]);

// bajar → [stream, content_type, content_length]
[$stream, $mime, $len] = $r2->getObject('mi-bucket', 'carpeta/doc.pdf');

// ¿existe?
$r2->exists('mi-bucket', 'carpeta/doc.pdf');   // bool

// borrar
$r2->deleteObject('mi-bucket', 'carpeta/doc.pdf');
```

## Variables de entorno (`.env`)
```
R2_ACCOUNT_ID=…                  # se usa para armar el endpoint si falta R2_ENDPOINT
R2_ACCESS_KEY_ID=…               # Access Key de la cuenta R2
R2_SECRET_ACCESS_KEY=…           # Secret de la cuenta R2
R2_BUCKET=…                      # bucket privado (objetos sensibles)
R2_BUCKET_PUBLIC=…               # bucket público (imágenes de convenios)
R2_PUBLIC_URL=…                  # URL pública del bucket público (CDN/custom domain)
R2_ENDPOINT=…                    # https://<ACCOUNT_ID>.r2.cloudflarestorage.com
```
Nunca pongas credenciales en el código ni en archivos versionados.

## Observaciones
- **SigV4**: firma las peticiones con `AWS4-HMAC-SHA256` (region `auto`). El `x-amz-content-sha256`
  se calcula sobre el payload real de la petición.
- **Transporte HTTP**: usa `curl` si está disponible; si no, cae a streams de PHP (`http://`).
- Los errores HTTP >= 400 lanzan `RuntimeException` con el cuerpo devuelto.