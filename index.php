/*
 * Remediación F-01: Vulnerabilidad SSRF / LFI
 * Se agregó validación del parámetro $_GET["data"] antes de usarlo en file_get_contents().
 * - Validación de scheme (solo http/https)
 * - Allowlist de dominios permitidos
 * - Bloqueo de rangos IP privados y link-local (IMDS, VPC interna)
 * - Reemplazo de file_get_contents() por cURL con opciones de seguridad
 */

<?php
$root = $_SERVER['DOCUMENT_ROOT'];
$envFilepath = "$root/sbfutils-printer/.env";
if (is_file($envFilepath)) {
    $file = new \SplFileObject($envFilepath);
    while (false === $file->eof()) {
        putenv(trim($file->fgets()));
    }
}

$dataPrinter = getenv('PRINTER') ?: 'default value';
$url = $_GET["data"] ?? '';

// 1. Validar que sea una URL HTTP/HTTPS válida (bloquea file://, gopher://, dict://, etc.)
$parsed = parse_url($url);
if (!$parsed || !in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
    http_response_code(400);
    exit("Invalid URL scheme.");
}

// 2. Allowlist de dominios permitidos
$allowedHosts = ['prd.sbfdocs.com'];
if (!in_array($parsed['host'] ?? '', $allowedHosts, true)) {
    http_response_code(403);
    exit("Host not allowed.");
}

// 3. Bloquear rangos privados/link-local (IMDS, VPC interna)
$ip = gethostbyname($parsed['host']);
if (
    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
) {
    http_response_code(403);
    exit("Private/reserved IP range not allowed.");
}

// 4. Usar cURL con opciones de seguridad en lugar de file_get_contents
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,   // evita redirect
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
]);
$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($content === false || $httpCode !== 200) {
    exit("File downloading failed.");
}

$tmpdir = sys_get_temp_dir();
$file = tempnam($tmpdir, 'ctk');
file_put_contents($file, $content);
copy($file, $dataPrinter);
unlink($file);

echo "File downloaded successfully";
?>
<script>window.close();</script>