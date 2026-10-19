<?php
declare(strict_types=1);

function url(string $path = ''): string
{
    return APP_URL . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function t(string $key, array $replace = []): string
{
    global $translations;
    $text = $translations[$key] ?? $key;
    foreach ($replace as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }
    return $text;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    if ($submitted === '' || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your session has expired. Please go back and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function customer(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function admin_user(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function require_customer(): array
{
    $user = customer();
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        flash('warning', t('login_required'));
        redirect('auth/login.php');
    }
    return $user;
}

function require_admin(): array
{
    $admin = admin_user();
    if (!$admin || (int) $admin['is_active'] !== 1) {
        unset($_SESSION['admin_id']);
        redirect('admin/login.php');
    }
    return $admin;
}

function order_number(): string
{
    return 'SHE-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function money(float|string $amount, string $currency = 'MMK'): string
{
    $decimals = $currency === 'MMK' ? 0 : 2;
    return number_format((float) $amount, $decimals) . ' ' . e($currency);
}

function status_class(string $status): string
{
    return match ($status) {
        'completed', 'approved', 'active', 'resolved' => 'success',
        'rejected', 'cancelled', 'blocked' => 'danger',
        'processing', 'payment_reviewing' => 'info',
        default => 'warning',
    };
}

function notify_user(int $userId, string $title, string $message, ?int $orderId = null): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, order_id, title, message) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $orderId, $title, $message]);
}

function log_admin(int $adminId, string $action, string $entityType = '', ?int $entityId = null): void
{
    $stmt = db()->prepare('INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$adminId, $action, $entityType ?: null, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function validate_image_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Please select a valid image file.'];
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        return [false, 'The image must be 5 MB or smaller.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return [false, 'Only JPG, PNG and WebP images are accepted.'];
    }
    return [true, $allowed[$mime]];
}

function upload_public_image(array $file, string $folder, string $prefix = 'image'): array
{
    [$valid, $result] = validate_image_upload($file);
    if (!$valid) return [false, $result];

    $folder = trim(preg_replace('/[^a-z0-9_-]/i', '', $folder));
    if ($folder === '') return [false, 'Invalid upload folder.'];
    $directory = BASE_PATH . '/uploads/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [false, 'The upload folder could not be created.'];
    }
    $filename = preg_replace('/[^a-z0-9_-]/i', '', $prefix) . '_' . bin2hex(random_bytes(12)) . '.' . $result;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        return [false, 'The image could not be saved.'];
    }
    return [true, 'uploads/' . $folder . '/' . $filename];
}

function delete_public_upload(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) return;
    $absolute = realpath(BASE_PATH . '/' . $path);
    $uploads = realpath(BASE_PATH . '/uploads');
    if ($absolute && $uploads && str_starts_with($absolute, $uploads . DIRECTORY_SEPARATOR) && is_file($absolute)) {
        unlink($absolute);
    }
}

function public_image(?string $path, string $fallback): string
{
    if ($path && is_file(BASE_PATH . '/' . $path)) return url($path);
    return url('assets/images/' . $fallback);
}

function mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($domain === '') return '***';
    $localVisible = mb_substr($local, 0, min(2, max(1, mb_strlen($local))));
    $domainParts = explode('.', $domain);
    $domainName = array_shift($domainParts) ?: '';
    $suffix = $domainParts ? '.' . implode('.', $domainParts) : '';
    return $localVisible . str_repeat('*', max(3, mb_strlen($local) - mb_strlen($localVisible))) . '@'
        . mb_substr($domainName, 0, 1) . str_repeat('*', max(3, mb_strlen($domainName) - 1)) . $suffix;
}

function shop_settings(): array
{
    static $settings;
    if (is_array($settings)) return $settings;
    $settings = [
        'shop_name' => APP_NAME,
        'logo_path' => null,
        'favicon_path' => null,
        'support_email' => '',
        'contact_phone' => '',
        'kbzpay_name' => (string) env_value('KBZPAY_NAME', ''),
        'kbzpay_phone' => (string) env_value('KBZPAY_PHONE', ''),
        'wave_name' => (string) env_value('WAVE_NAME', ''),
        'wave_phone' => (string) env_value('WAVE_PHONE', ''),
    ];
    try {
        $row = db()->query('SELECT * FROM shop_settings WHERE id=1')->fetch();
        if ($row) {
            foreach ($row as $key => $value) {
                if ($value !== null && $value !== '') $settings[$key] = $value;
            }
        }
    } catch (Throwable) {
        // Allows the installer to open the site before running database_update.sql.
    }
    return $settings;
}

function send_otp_email(string $email, string $otp): bool
{
    $subject = 'SHE Game Shop verification code';
    $body = "Your SHE Game Shop verification code is: {$otp}\n\nThis code expires in 5 minutes. Do not share it with anyone.";
    $mode = (string) env_value('MAIL_MODE', 'log');

    if ($mode !== 'smtp') {
        $line = sprintf("[%s] To: %s | OTP: %s%s", date('c'), $email, $otp, PHP_EOL);
        return file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }

    $autoload = BASE_PATH . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }
    require_once $autoload;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) env_value('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = (string) env_value('MAIL_USERNAME');
        $mail->Password = (string) env_value('MAIL_APP_PASSWORD');
        $mail->SMTPSecure = (string) env_value('MAIL_ENCRYPTION', 'tls');
        $mail->Port = (int) env_value('MAIL_PORT', 587);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string) env_value('MAIL_FROM_ADDRESS'), (string) env_value('MAIL_FROM_NAME', APP_NAME));
        $mail->addAddress($email);
        $mail->Subject = $subject;
        $mail->Body = $body;
        return $mail->send();
    } catch (Throwable) {
        return false;
    }
}

function issue_otp(int $userId, string $email, string $purpose = 'verify_email'): bool
{
    $otp = (string) random_int(100000, 999999);
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    db()->prepare('DELETE FROM email_otps WHERE user_id = ? AND purpose = ?')->execute([$userId, $purpose]);
    $stmt = db()->prepare('INSERT INTO email_otps (user_id, purpose, otp_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))');
    $stmt->execute([$userId, $purpose, $hash]);
    return send_otp_email($email, $otp);
}
