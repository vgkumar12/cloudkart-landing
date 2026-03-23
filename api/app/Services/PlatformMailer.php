<?php

namespace App\Services;

/**
 * Lightweight socket-based SMTP mailer for the landing/platform API.
 * Reads SMTP settings from platform config constants.
 * Falls back to PHP mail() if SMTP not configured.
 */
class PlatformMailer {

    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;

    public function __construct() {
        $this->host       = defined('SMTP_HOST')     ? SMTP_HOST     : '';
        $this->port       = defined('SMTP_PORT')     ? (int)SMTP_PORT : 587;
        $this->encryption = defined('SMTP_ENC')      ? SMTP_ENC      : 'tls';
        $this->username   = defined('SMTP_USER')     ? SMTP_USER     : '';
        $this->password   = defined('SMTP_PASS')     ? SMTP_PASS     : '';
        $this->fromEmail  = defined('SMTP_FROM')     ? SMTP_FROM     : 'noreply@cloudkart24.com';
        $this->fromName   = defined('SMTP_FROM_NAME')? SMTP_FROM_NAME : 'CloudKart';
    }

    public function isConfigured(): bool {
        return !empty($this->host) && !empty($this->username);
    }

    /**
     * Send welcome email after store provisioning.
     */
    public static function sendWelcome(string $to, string $toName, string $storeName, string $storeUrl, string $adminUrl): void {
        try {
            $mailer = new self();
            $subject = "Your CloudKart Store is Ready — {$storeName}";
            $html = self::buildWelcomeHtml($toName, $storeName, $storeUrl, $adminUrl);
            $mailer->send($to, $toName, $subject, $html);
        } catch (\Throwable $e) {
            error_log("PlatformMailer::sendWelcome failed: " . $e->getMessage());
        }
    }

    public function send(string $to, string $toName, string $subject, string $html): bool {
        if ($this->isConfigured()) {
            return $this->sendSmtp($to, $toName, $subject, $html);
        }
        // Fallback to PHP mail()
        return $this->sendPhpMail($to, $toName, $subject, $html);
    }

    private function sendPhpMail(string $to, string $toName, string $subject, string $html): bool {
        $from = $this->fromEmail;
        $fromName = $this->fromName;
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        return mail($to, $subject, $html, $headers);
    }

    private function sendSmtp(string $to, string $toName, string $subject, string $html): bool {
        $host = $this->host;
        $port = $this->port;
        $enc  = strtolower($this->encryption);

        // Connect
        if ($enc === 'ssl') {
            $conn = @fsockopen("ssl://{$host}", $port, $errno, $errstr, 10);
        } else {
            $conn = @fsockopen($host, $port, $errno, $errstr, 10);
        }
        if (!$conn) {
            throw new \Exception("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($conn, 15);

        $this->expect($conn, '220');

        $this->send_cmd($conn, "EHLO " . gethostname());
        $this->read_all($conn); // Read multi-line EHLO response

        if ($enc === 'tls') {
            $this->send_cmd($conn, "STARTTLS");
            $this->expect($conn, '220');
            stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->send_cmd($conn, "EHLO " . gethostname());
            $this->read_all($conn);
        }

        if (!empty($this->username)) {
            $this->send_cmd($conn, "AUTH LOGIN");
            $this->expect($conn, '334');
            $this->send_cmd($conn, base64_encode($this->username));
            $this->expect($conn, '334');
            $this->send_cmd($conn, base64_encode($this->password));
            $this->expect($conn, '235');
        }

        $fromEmail = $this->fromEmail;
        $fromName  = $this->fromName;

        $this->send_cmd($conn, "MAIL FROM:<{$fromEmail}>");
        $this->expect($conn, '250');
        $this->send_cmd($conn, "RCPT TO:<{$to}>");
        $this->expect($conn, '250');
        $this->send_cmd($conn, "DATA");
        $this->expect($conn, '354');

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $date = date('r');
        $msgId = '<' . time() . '.' . rand(1000, 9999) . '@cloudkart>';

        $message  = "Date: {$date}\r\n";
        $message .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $message .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$to}>\r\n";
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "Message-ID: {$msgId}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($html));
        $message .= "\r\n.";

        fwrite($conn, $message . "\r\n");
        $this->expect($conn, '250');

        $this->send_cmd($conn, "QUIT");
        fclose($conn);
        return true;
    }

    private function send_cmd($conn, string $cmd): void {
        fwrite($conn, $cmd . "\r\n");
    }

    private function expect($conn, string $code): string {
        $response = fgets($conn, 512);
        if (substr($response, 0, 3) !== $code) {
            throw new \Exception("SMTP error (expected {$code}): " . trim($response));
        }
        return $response;
    }

    private function read_all($conn): string {
        $out = '';
        while ($line = fgets($conn, 512)) {
            $out .= $line;
            if ($line[3] === ' ') break; // Last line of multi-line response
        }
        return $out;
    }

    private static function buildWelcomeHtml(string $name, string $storeName, string $storeUrl, string $adminUrl): string {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:40px 20px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:600px;">

      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#6B46C1 0%,#8B5CF6 100%);padding:40px 48px;text-align:center;">
        <h1 style="margin:0;color:#fff;font-size:28px;font-weight:800;letter-spacing:-0.5px;">🎉 Your store is live!</h1>
        <p style="margin:12px 0 0;color:rgba(255,255,255,0.85);font-size:16px;">Welcome to CloudKart, {$name}</p>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:48px;">
        <p style="margin:0 0 24px;font-size:16px;color:#374151;line-height:1.6;">
          Congratulations! Your CloudKart store <strong>{$storeName}</strong> has been successfully provisioned and is ready for business.
        </p>

        <!-- Store URLs -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:12px;padding:24px;margin-bottom:32px;">
          <tr><td>
            <p style="margin:0 0 16px;font-size:13px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:0.5px;">Your Store Links</p>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;">
                  <span style="font-size:13px;color:#6B7280;font-weight:600;">Storefront</span><br>
                  <a href="{$storeUrl}" style="font-size:15px;color:#7C3AED;font-weight:700;text-decoration:none;">{$storeUrl}</a>
                </td>
              </tr>
              <tr>
                <td style="padding:10px 0;">
                  <span style="font-size:13px;color:#6B7280;font-weight:600;">Admin Panel</span><br>
                  <a href="{$adminUrl}" style="font-size:15px;color:#7C3AED;font-weight:700;text-decoration:none;">{$adminUrl}</a>
                </td>
              </tr>
            </table>
          </td></tr>
        </table>

        <!-- Getting Started -->
        <p style="margin:0 0 16px;font-size:14px;font-weight:700;color:#111827;">Getting Started</p>
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr><td style="padding:8px 0 8px 20px;border-left:3px solid #8B5CF6;margin-bottom:12px;">
            <p style="margin:0;font-size:14px;color:#374151;">1. Log in to your admin panel using the email and password you set during signup.</p>
          </td></tr>
          <tr><td style="padding:8px 0 8px 20px;border-left:3px solid #8B5CF6;margin-bottom:12px;padding-top:16px;">
            <p style="margin:0;font-size:14px;color:#374151;">2. Add your products and categories from the Catalog section.</p>
          </td></tr>
          <tr><td style="padding:8px 0 8px 20px;border-left:3px solid #8B5CF6;padding-top:16px;">
            <p style="margin:0;font-size:14px;color:#374151;">3. Configure your payment methods and delivery settings.</p>
          </td></tr>
        </table>

        <!-- CTA -->
        <div style="text-align:center;margin-top:40px;">
          <a href="{$adminUrl}" style="display:inline-block;background:linear-gradient(135deg,#6B46C1,#8B5CF6);color:#fff;text-decoration:none;padding:14px 40px;border-radius:10px;font-size:16px;font-weight:700;letter-spacing:-0.3px;">
            Open Admin Panel →
          </a>
        </div>
      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#F9FAFB;padding:24px 48px;text-align:center;border-top:1px solid #E5E7EB;">
        <p style="margin:0;font-size:13px;color:#9CA3AF;">© {$year} CloudKart · All rights reserved</p>
        <p style="margin:8px 0 0;font-size:12px;color:#D1D5DB;">You received this email because you signed up for CloudKart.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }
}
