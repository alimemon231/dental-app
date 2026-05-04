<?php
/**
 * includes/EmailSender.php
 * Reusable email utility for the system.
 */

class EmailSender {
    private static $fromName = 'Ouray Dental Management';
    private static $fromEmail = 'noreply@ouraydentalmanagement.com';
    private static $adminEmail = 'Ourayfax@gmail.com';

    /**
     * Sends a generic email and also bccs/notifies the admin if requested.
     */
    public static function send(string $toEmail, string $subject, string $messageBody): bool {
        $headers = "From: " . self::$fromName . " <" . self::$fromEmail . ">\r\n";
        $headers .= "Reply-To: " . self::$fromEmail . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        // Send to the intended recipient
        $userMail = mail($toEmail, $subject, $messageBody, $headers);
        
        // Also send notification to the admin/fax email
        $adminSubject = "NOTIFICATION: " . $subject;
        mail(self::$adminEmail, $adminSubject, $messageBody, $headers);

        return $userMail;
    }
}