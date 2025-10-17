<?php
/**
 * CLI Email Recovery Script
 *
 * Usage:
 *   php scripts/recover-emails.php --help
 *   php scripts/recover-emails.php --list
 *   php scripts/recover-emails.php --revalidate
 *   php scripts/recover-emails.php --recover-all
 *   php scripts/recover-emails.php --recover-email="email@domain.com"
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/EmailValidator.php';

function showHelp() {
    echo "Email Recovery CLI Tool\n";
    echo "=======================\n\n";
    echo "Usage:\n";
    echo "  php scripts/recover-emails.php [options]\n\n";
    echo "Options:\n";
    echo "  --help                     Show this help message\n";
    echo "  --list                     List all rejected emails\n";
    echo "  --revalidate               Re-validate all rejected emails (dry run)\n";
    echo "  --recover-all              Recover all valid rejected emails\n";
    echo "  --recover-email=EMAIL      Recover specific email address\n";
    echo "  --stats                    Show rejection statistics\n\n";
    echo "Examples:\n";
    echo "  php scripts/recover-emails.php --list\n";
    echo "  php scripts/recover-emails.php --recover-email=\"user@gmail.com\"\n";
    echo "  php scripts/recover-emails.php --recover-all\n\n";
}

function listRejectedEmails($pdo) {
    echo "📋 Rejected Emails List\n";
    echo "========================\n\n";

    $stmt = $pdo->query("
        SELECT email, rejection_reason, submitted_at
        FROM rejected_emails
        ORDER BY submitted_at DESC
    ");
    $emails = $stmt->fetchAll();

    if (empty($emails)) {
        echo "✅ No rejected emails found!\n";
        return;
    }

    foreach ($emails as $email) {
        echo "📧 " . $email['email'] . "\n";
        echo "   Reason: " . $email['rejection_reason'] . "\n";
        echo "   Date: " . $email['submitted_at'] . "\n\n";
    }

    echo "Total: " . count($emails) . " rejected emails\n";
}

function showStats($pdo) {
    echo "📊 Rejection Statistics\n";
    echo "=======================\n\n";

    $stmt = $pdo->query("
        SELECT rejection_reason, COUNT(*) as count
        FROM rejected_emails
        GROUP BY rejection_reason
        ORDER BY count DESC
    ");
    $stats = $stmt->fetchAll();

    if (empty($stats)) {
        echo "✅ No rejected emails found!\n";
        return;
    }

    foreach ($stats as $stat) {
        echo "• " . $stat['rejection_reason'] . ": " . $stat['count'] . " emails\n";
    }
}

function revalidateEmails($pdo, $validator) {
    echo "🔍 Re-validating All Rejected Emails\n";
    echo "=====================================\n\n";

    $stmt = $pdo->query("SELECT id, email, rejection_reason FROM rejected_emails");
    $emails = $stmt->fetchAll();

    if (empty($emails)) {
        echo "✅ No rejected emails to revalidate!\n";
        return;
    }

    $valid_count = 0;
    $invalid_count = 0;

    foreach ($emails as $email) {
        $validation = $validator->validate($email['email']);

        echo "📧 " . $email['email'] . "\n";
        echo "   Old reason: " . $email['rejection_reason'] . "\n";

        if ($validation['valid']) {
            echo "   ✅ NOW VALID (Score: " . $validation['score'] . ")\n";
            if ($validation['email'] !== $email['email']) {
                echo "   📝 Corrected to: " . $validation['email'] . "\n";
            }
            $valid_count++;
        } else {
            echo "   ❌ Still invalid: " . $validation['reason'] . "\n";
            $invalid_count++;
        }
        echo "\n";
    }

    echo "Summary:\n";
    echo "✅ Now valid: $valid_count emails\n";
    echo "❌ Still invalid: $invalid_count emails\n";
    echo "\nUse --recover-all to recover the valid emails.\n";
}

function recoverAllEmails($pdo, $validator) {
    echo "🔄 Recovering All Valid Rejected Emails\n";
    echo "========================================\n\n";

    $stmt = $pdo->query("SELECT id, email FROM rejected_emails");
    $emails = $stmt->fetchAll();

    if (empty($emails)) {
        echo "✅ No rejected emails to recover!\n";
        return;
    }

    $recovered = 0;
    $failed = 0;

    foreach ($emails as $email) {
        $validation = $validator->validate($email['email']);

        if ($validation['valid']) {
            // Check if already subscribed
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE email = ?");
            $stmt->execute([$validation['email']]);

            if ($stmt->fetchColumn() == 0) {
                // Add to subscribers
                $stmt = $pdo->prepare("
                    INSERT INTO subscribers (email, subscribed_at, validation_score, ip_address, status, unsubscribe_token)
                    VALUES (?, NOW(), ?, '127.0.0.1', 'active', ?)
                ");
                $stmt->execute([
                    $validation['email'],
                    $validation['score'],
                    generateSecureToken()
                ]);

                echo "✅ Recovered: " . $validation['email'] . "\n";
            } else {
                echo "ℹ️  Already subscribed: " . $validation['email'] . "\n";
            }

            // Remove from rejected
            $stmt = $pdo->prepare("DELETE FROM rejected_emails WHERE id = ?");
            $stmt->execute([$email['id']]);

            $recovered++;
        } else {
            echo "❌ Still invalid: " . $email['email'] . " (" . $validation['reason'] . ")\n";
            $failed++;
        }
    }

    echo "\n🎉 Recovery Complete!\n";
    echo "✅ Recovered: $recovered emails\n";
    echo "❌ Failed: $failed emails\n";
}

function recoverSpecificEmail($pdo, $validator, $email_address) {
    echo "🔄 Recovering Specific Email: $email_address\n";
    echo "=============================================\n\n";

    // Check if email is in rejected list
    $stmt = $pdo->prepare("SELECT id, rejection_reason FROM rejected_emails WHERE email = ?");
    $stmt->execute([$email_address]);
    $rejected = $stmt->fetch();

    if (!$rejected) {
        echo "❌ Email not found in rejected list: $email_address\n";
        return;
    }

    echo "📧 Found rejected email: $email_address\n";
    echo "   Original rejection reason: " . $rejected['rejection_reason'] . "\n\n";

    // Re-validate
    $validation = $validator->validate($email_address);

    if ($validation['valid']) {
        // Check if already subscribed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE email = ?");
        $stmt->execute([$validation['email']]);

        if ($stmt->fetchColumn() == 0) {
            // Add to subscribers
            $stmt = $pdo->prepare("
                INSERT INTO subscribers (email, subscribed_at, validation_score, ip_address, status, unsubscribe_token)
                VALUES (?, NOW(), ?, '127.0.0.1', 'active', ?)
            ");
            $stmt->execute([
                $validation['email'],
                $validation['score'],
                generateSecureToken()
            ]);

            echo "✅ Successfully recovered and subscribed: " . $validation['email'] . "\n";
            if ($validation['email'] !== $email_address) {
                echo "   📝 Email was corrected during recovery\n";
            }
            echo "   📊 Validation score: " . $validation['score'] . "\n";
        } else {
            echo "ℹ️  Email was already subscribed: " . $validation['email'] . "\n";
        }

        // Remove from rejected
        $stmt = $pdo->prepare("DELETE FROM rejected_emails WHERE id = ?");
        $stmt->execute([$rejected['id']]);

        echo "🗑️  Removed from rejected emails list\n";
    } else {
        echo "❌ Email is still invalid: " . $validation['reason'] . "\n";
        echo "   Cannot recover this email address\n";
    }
}

// Parse command line arguments
$options = getopt('', ['help', 'list', 'revalidate', 'recover-all', 'recover-email:', 'stats']);

if (isset($options['help']) || empty($options)) {
    showHelp();
    exit(0);
}

try {
    $pdo = getDbConnection();
    $validator = new EmailValidator($disposable_domains);

    if (isset($options['list'])) {
        listRejectedEmails($pdo);
    } elseif (isset($options['stats'])) {
        showStats($pdo);
    } elseif (isset($options['revalidate'])) {
        revalidateEmails($pdo, $validator);
    } elseif (isset($options['recover-all'])) {
        echo "⚠️  This will recover ALL valid rejected emails. Are you sure? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim(strtolower($line)) === 'y') {
            recoverAllEmails($pdo, $validator);
        } else {
            echo "❌ Operation cancelled.\n";
        }
    } elseif (isset($options['recover-email'])) {
        recoverSpecificEmail($pdo, $validator, $options['recover-email']);
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>