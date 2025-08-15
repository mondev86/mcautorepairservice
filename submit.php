<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura y saneo
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $messageContent = trim($_POST['message'] ?? '');

    // Validaciones simples
    if ($name === '' || $phone === '' || $messageContent === '') {
        http_response_code(400);
        echo 'Faltan datos obligatorios.';
        exit;
    }
    if (mb_strlen($name) > 100 || mb_strlen($phone) > 30 || mb_strlen($messageContent) > 5000) {
        http_response_code(400);
        echo 'Datos demasiado largos.';
        exit;
    }

    // (Opcional) Honeypot contra bots: añade <input type="text" name="website" style="display:none">
    if (!empty($_POST['website'] ?? '')) {
        http_response_code(200);
        echo 'OK';
        exit;
    }

    // Config SMTP desde .env
    $smtpHost   = $_ENV['SMTP_HOST'] ?? '';
    $smtpPort   = (int)($_ENV['SMTP_PORT'] ?? 587);
    $smtpSecure = strtolower($_ENV['SMTP_SECURE'] ?? 'tls'); // tls|ssl|none
    $smtpUser   = $_ENV['SMTP_USER'] ?? '';
    $smtpPass   = $_ENV['SMTP_PASS'] ?? '';

    $mailFrom       = $_ENV['MAIL_FROM'] ?? $smtpUser;
    $mailFromName   = $_ENV['MAIL_FROM_NAME'] ?? 'Formulario Web';
    $mailTo         = $_ENV['MAIL_TO'] ?? $smtpUser;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;

        if ($smtpSecure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $smtpPort ?: 465;
        } elseif ($smtpSecure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort ?: 587;
        } else {
            $mail->SMTPSecure = false;
            $mail->Port = $smtpPort ?: 25;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($mailFrom, $mailFromName);
        $mail->addAddress($mailTo);

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
        $safeMsg = nl2br(htmlspecialchars($messageContent, ENT_QUOTES, 'UTF-8'));

        $mail->isHTML(true);
        $mail->Subject = 'Nueva solicitud desde el formulario';
        $mail->Body = "
            <strong>Nombre:</strong> {$safeName}<br>
            <strong>Teléfono:</strong> {$safePhone}<br>
            <strong>Mensaje:</strong><br>{$safeMsg}
        ";
        $mail->AltBody = "Nombre: {$name}\nTeléfono: {$phone}\nMensaje:\n{$messageContent}";

        $mail->send();
        echo 'Solicitud enviada exitosamente.';
    } catch (Exception $e) {
        // En producción, evita exponer detalles del error
        http_response_code(500);
        echo 'Error al enviar. Inténtalo más tarde.';
    }
}
