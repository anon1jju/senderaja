<?php
/**
 * CLI Script untuk mengirim email
 * 
 * Usage:  php send.php
 */

require_once 'functions.php';
require_once 'EmailSender.php';

// Load config
$config = require 'config.php';

echo "\n";
echo "╔════════════════════════════════════════╗\n";
echo "║      POSTMARK EMAIL SENDER v1.0        ║\n";
echo "╚════════════════════════════════════════╝\n";
echo "\n";

// Info
echo "Template  : {$config['paths']['letter_file']}\n";
echo "Email List:  {$config['paths']['email_list']}\n";
echo "Subject   : {$config['subject']}\n";
echo "Accounts  : " . count($config['accounts']) . " account(s)\n";
echo "\n";

// Tampilkan akun dengan from_name yang sudah diproses
echo "Sender Accounts (preview):\n";
foreach ($config['accounts'] as $index => $account) {
    $processedName = replacePlaceholder($account['from_name']);
    echo "  #" . ($index + 1) . ": {$processedName} <{$account['from_email']}>\n";
}
echo "\n";

// Validasi
if (! file_exists($config['paths']['letter_file'])) {
    echo "❌ ERROR: Template file not found\n";
    exit(1);
}

if (!file_exists($config['paths']['email_list'])) {
    echo "❌ ERROR: Email list not found\n";
    exit(1);
}

// Hitung jumlah email
$lines = file($config['paths']['email_list'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$emailCount = 0;
foreach ($lines as $line) {
    $line = trim($line);
    if (!empty($line) && strpos($line, '#') !== 0) {
        $parts = explode(',', $line);
        if (filter_var(trim($parts[0]), FILTER_VALIDATE_EMAIL)) {
            $emailCount++;
        }
    }
}

echo "Emails to send: {$emailCount}\n\n";
echo "Press ENTER to start or CTRL+C to cancel.. .\n";
fgets(STDIN);

// Jalankan
$sender = new EmailSender($config);
$result = $sender->run();

echo "\n";
if ($result['success']) {
    echo "✅ Completed!  Success:  {$result['total_success']}, Failed: {$result['total_failed']}\n";
} else {
    echo "❌ Error: " . $result['message'] . "\n";
}
