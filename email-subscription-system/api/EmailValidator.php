<?php
/**
 * Email Subscription System - Email Validation Class
 */

class EmailValidator {
    private $disposable_domains;

    public function __construct($disposable_domains = []) {
        $this->disposable_domains = $disposable_domains;
    }

    /**
     * Comprehensive email validation
     */
    public function validate($email) {
        $result = [
            'valid' => false,
            'email' => $email,
            'reason' => '',
            'score' => 0
        ];

        // Basic format validation
        if (!$this->isValidFormat($email)) {
            $result['reason'] = 'Invalid email format';
            return $result;
        }

        // Check for common typos
        $corrected_email = $this->fixCommonTypos($email);
        if ($corrected_email !== $email) {
            $result['email'] = $corrected_email;
            $email = $corrected_email;
            $result['score'] += 10; // Bonus for auto-correction
        }

        // Check if disposable email
        if ($this->isDisposableEmail($email)) {
            $result['reason'] = 'Disposable email not allowed';
            return $result;
        }

        // Domain validation
        $domain_check = $this->validateDomain($email);
        if (!$domain_check['valid']) {
            $result['reason'] = $domain_check['reason'];
            return $result;
        }

        $result['score'] += $domain_check['score'];

        // If we get here, email is valid
        $result['valid'] = true;
        $result['score'] = min(100, $result['score'] + 50); // Base score + bonuses

        return $result;
    }

    /**
     * Basic email format validation
     */
    private function isValidFormat($email) {
        if (strlen($email) > 254) return false;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        // Additional RFC checks
        list($local, $domain) = explode('@', $email);
        if (strlen($local) > 64) return false;
        if (strlen($domain) > 253) return false;

        return true;
    }

    /**
     * Fix common email typos
     */
    private function fixCommonTypos($email) {
        $typos = [
            'gmail.co' => 'gmail.com',
            'gmail.cm' => 'gmail.com',
            'gmai.com' => 'gmail.com',
            'gmial.com' => 'gmail.com',
            'yahoo.co' => 'yahoo.com',
            'yahoo.cm' => 'yahoo.com',
            'hotmail.co' => 'hotmail.com',
            'hotmail.cm' => 'hotmail.com',
            'outlook.co' => 'outlook.com',
            'outlook.cm' => 'outlook.com'
        ];

        foreach ($typos as $typo => $correct) {
            // Use str_ends_with to ensure we're matching the actual domain, not a substring
            if (str_ends_with($email, '@' . $typo)) {
                return str_replace('@' . $typo, '@' . $correct, $email);
            }
        }

        return $email;
    }

    /**
     * Check if email is from a disposable service
     */
    private function isDisposableEmail($email) {
        if (!ENABLE_DISPOSABLE_EMAIL_CHECK) return false;

        $domain = strtolower(substr(strrchr($email, '@'), 1));
        return in_array($domain, $this->disposable_domains);
    }

    /**
     * Validate email domain
     */
    private function validateDomain($email) {
        $result = ['valid' => true, 'reason' => '', 'score' => 0];

        $domain = substr(strrchr($email, '@'), 1);

        // Check for valid characters
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
            $result['valid'] = false;
            $result['reason'] = 'Invalid domain characters';
            return $result;
        }

        // Check if domain has MX record
        if (!$this->hasMxRecord($domain)) {
            $result['valid'] = false;
            $result['reason'] = 'Domain has no MX record';
            return $result;
        }

        // Popular domains get higher score
        $popular_domains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'icloud.com', 'aol.com', 'protonmail.com'
        ];

        if (in_array(strtolower($domain), $popular_domains)) {
            $result['score'] += 20;
        }

        return $result;
    }

    /**
     * Check if domain has MX record
     */
    private function hasMxRecord($domain) {
        // In local development, skip MX check for localhost/development domains
        if (in_array($domain, ['localhost', 'example.com', 'test.com'])) {
            return true;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }

    /**
     * Check rate limiting
     */
    public function checkRateLimit($ip, $pdo) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM subscribers
            WHERE ip_address = ?
            AND DATE(subscribed_at) = CURDATE()
        ");
        $stmt->execute([$ip]);
        $result = $stmt->fetch();

        return $result['count'] < MAX_DAILY_SUBSCRIPTIONS_PER_IP;
    }
}
?>