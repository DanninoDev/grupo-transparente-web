<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Carga simple de .env
 * Busca el archivo en la raíz pública: public_html/.env
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

function envValue(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

function jsonResponse(int $status, bool $ok, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => $ok,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Cargar .env desde la raíz pública
loadEnv(__DIR__ . '/../.env');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, false, 'Método no permitido.');
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    jsonResponse(400, false, 'Datos inválidos.');
}

// Honeypot anti-spam
$website = trim((string)($data['website'] ?? ''));
if ($website !== '') {
    jsonResponse(400, false, 'Solicitud inválida.');
}

$nombre   = trim((string)($data['nombre'] ?? ''));
$empresa  = trim((string)($data['empresa'] ?? ''));
$email    = trim((string)($data['email'] ?? ''));
$telefono = trim((string)($data['telefono'] ?? ''));
$mensaje  = trim((string)($data['mensaje'] ?? ''));

if ($nombre === '' || $email === '' || $mensaje === '') {
    jsonResponse(422, false, 'Completa los campos obligatorios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(422, false, 'El correo no es válido.');
}

$smtpHost     = envValue('SMTP_HOST');
$smtpPort     = (int)(envValue('SMTP_PORT', '465'));
$smtpSecure   = strtolower((string)envValue('SMTP_SECURE', 'ssl'));
$smtpUsername = envValue('SMTP_USERNAME');
$smtpPassword = envValue('SMTP_PASSWORD');
$fromEmail    = envValue('SMTP_FROM_EMAIL', $smtpUsername);
$fromName     = envValue('SMTP_FROM_NAME', 'Formulario Web');
$toEmail      = envValue('SMTP_TO_EMAIL', $smtpUsername);
$toName       = envValue('SMTP_TO_NAME', 'Contacto');

if (!$smtpHost || !$smtpUsername || !$smtpPassword || !$fromEmail || !$toEmail) {
    jsonResponse(500, false, 'Falta configuración SMTP en el servidor.');
}

try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->Port = $smtpPort;
    $mail->CharSet = 'UTF-8';

    if ($smtpSecure === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($toEmail, $toName);
    $mail->addReplyTo($email, $nombre);

    $empresaHtml  = $empresa !== '' ? htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') : 'No especificada';
    $telefonoHtml = $telefono !== '' ? htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') : 'No especificado';

    $mail->isHTML(true);
    $mail->Subject = 'Nuevo mensaje desde el formulario de contacto';

    $mail->Body = '
        <div style="font-family:Arial,Helvetica,sans-serif; color:#0f172a; line-height:1.6;">
            <h2 style="margin:0 0 16px;">Nuevo mensaje de contacto</h2>
            <p><strong>Nombre:</strong> ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Empresa:</strong> ' . $empresaHtml . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Teléfono:</strong> ' . $telefonoHtml . '</p>
            <p><strong>Mensaje:</strong><br>' . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) . '</p>
        </div>
    ';

    $mail->AltBody =
        "Nuevo mensaje de contacto\n\n" .
        "Nombre: {$nombre}\n" .
        "Empresa: " . ($empresa !== '' ? $empresa : 'No especificada') . "\n" .
        "Email: {$email}\n" .
        "Teléfono: " . ($telefono !== '' ? $telefono : 'No especificado') . "\n\n" .
        "Mensaje:\n{$mensaje}";

    $mail->send();

    jsonResponse(200, true, 'Mensaje enviado correctamente.');
} catch (Exception $e) {
    jsonResponse(500, false, 'No se pudo enviar el correo. Error: ' . $e->getMessage());
}