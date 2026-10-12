<?php
declare(strict_types=1);

// SERV QR image endpoint.  This endpoint intentionally lives at the web root
// because /app/ is protected by .htaccess and is not publicly accessible.
$data = trim((string)($_GET['d'] ?? ''));
if ($data === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Missing QR data');
}
if (strlen($data) > 2000) {
    http_response_code(414);
    header('Content-Type: text/plain; charset=utf-8');
    exit('QR data too long');
}

$target = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&margin=12&data=' . rawurlencode($data);
header('Cache-Control: public, max-age=3600');
header('Location: ' . $target, true, 302);
exit;
