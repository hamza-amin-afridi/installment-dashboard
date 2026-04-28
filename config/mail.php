<?php
/**
 * Mail Configuration
 * Uses PHP mail() by default.
 * Set MAIL_FROM to your cPanel email address.
 */

define('MAIL_FROM',    'noreply@yourdomain.com');
define('MAIL_FROM_NAME', APP_NAME);

/**
 * Send a plain-text email
 */
function sendMail(string $to, string $subject, string $body): bool {
    $headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to, $subject, $body, $headers);
}

/**
 * Build password reset email body
 */
function buildResetEmail(string $name, string $resetLink): string {
    return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
  body{font-family:Poppins,Arial,sans-serif;background:#0f1117;color:#e8eaf6;margin:0;padding:20px}
  .card{background:#21253a;border-radius:12px;padding:32px;max-width:520px;margin:auto}
  h2{color:#6c63ff;margin-top:0}
  .btn{display:inline-block;background:#6c63ff;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600}
  .muted{color:#8892b0;font-size:13px}
</style></head><body>
<div class="card">
  <h2>🔑 Password Reset Request</h2>
  <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
  <p>You requested a password reset for your <strong>' . APP_NAME . '</strong> account.</p>
  <p>Click the button below to reset your password. This link expires in <strong>1 hour</strong>.</p>
  <p style="text-align:center;margin:32px 0">
    <a href="' . $resetLink . '" class="btn">Reset Password</a>
  </p>
  <p class="muted">If you did not request this, ignore this email. Your password will not change.</p>
  <p class="muted">Link: ' . $resetLink . '</p>
</div>
</body></html>';
}
