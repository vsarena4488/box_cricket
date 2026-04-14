<?php
// Email configuration using PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files
require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

class MailConfig {
    private $mail;
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);

        // Optional local config file for XAMPP/Laragon (no env var setup needed).
        // File should return an array like:
        // ['MAIL_USERNAME' => 'you@gmail.com', 'MAIL_PASSWORD' => 'app-password']
        $local_config = [];
        $local_config_file = __DIR__ . '/mail_secrets.php';
        if (file_exists($local_config_file)) {
            $loaded = require $local_config_file;
            if (is_array($loaded)) {
                $local_config = $loaded;
            }
        }
        
        // Read SMTP config from environment variables with safe fallbacks.
        $this->smtp_host = $local_config['MAIL_HOST'] ?? getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $this->smtp_port = (int) ($local_config['MAIL_PORT'] ?? getenv('MAIL_PORT') ?: 587);
        $this->smtp_user = $local_config['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: 'your-email@gmail.com';
        $this->smtp_pass = $local_config['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: 'your-app-password';
        $this->from_email = $local_config['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: $this->smtp_user;
        $this->from_name = $local_config['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'Box Cricket';
        $mail_encryption = strtolower($local_config['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?: 'tls');
        
        if ($this->smtp_user === 'your-email@gmail.com' || $this->smtp_pass === 'your-app-password') {
            throw new Exception(
                'SMTP credentials are not configured. Set MAIL_USERNAME and MAIL_PASSWORD (Gmail App Password).'
            );
        }
        
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host = $this->smtp_host;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $this->smtp_user;
        $this->mail->Password = $this->smtp_pass;
        $this->mail->SMTPSecure = ($mail_encryption === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = $this->smtp_port;
        $this->mail->Timeout = 20;
        
        // Sender
        $this->mail->setFrom($this->from_email, $this->from_name);
        
        // Content settings
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }
    
    public function sendOTP($to_email, $to_name, $otp) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to_email, $to_name);
            $this->mail->Subject = 'Password Reset OTP - Box Cricket';
            
            // HTML Body
            $this->mail->Body = $this->getOTPEmailHTML($to_name, $otp);
            
            // Plain text alternative
            $this->mail->AltBody = $this->getOTPEmailPlain($to_name, $otp);
            
            $this->mail->send();
            return ['success' => true, 'message' => 'OTP sent successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->mail->ErrorInfo];
        }
    }
    
    private function getOTPEmailHTML($name, $otp) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #0d6efd; padding-bottom: 20px; margin-bottom: 20px; }
                .logo { font-size: 24px; font-weight: bold; color: #0d6efd; }
                .otp-code { font-size: 32px; font-weight: bold; color: #0d6efd; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; letter-spacing: 5px; margin: 20px 0; }
                .timer { color: #dc3545; font-size: 12px; text-align: center; margin-top: 15px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>🏏 Box Cricket</div>
                </div>
                <h2>Hello, " . htmlspecialchars($name) . "!</h2>
                <p>We received a request to reset your password. Use the following OTP to verify your identity:</p>
                <div class='otp-code'>" . $otp . "</div>
                <p>This OTP is valid for <strong>2 minutes</strong> only.</p>
                <div class='timer'>⚠️ For security reasons, do not share this OTP with anyone.</div>
                <div class='footer'>
                    <p>If you didn't request this, please ignore this email or contact support.</p>
                    <p>&copy; " . date('Y') . " Box Cricket. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getOTPEmailPlain($name, $otp) {
        return "Hello $name,\n\n"
             . "We received a request to reset your password. Use the following OTP to verify your identity:\n\n"
             . "OTP: $otp\n\n"
             . "This OTP is valid for 2 minutes only.\n\n"
             . "If you didn't request this, please ignore this email.\n\n"
             . "Regards,\nBox Cricket Team";
    }
}
?>
