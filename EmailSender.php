<?php

require_once 'PostmarkMailer.php';

class EmailSender
{
    private $config;
    private $accounts;
    private $currentAccountIndex = 0;
    private $emailsSent = 0;
    private $emailsSentThisRest = 0;
    private $totalSuccess = 0;
    private $totalFailed = 0;
    private $startTime;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->accounts = $config['accounts'];
        $this->startTime = time();
        
        $logDir = dirname($config['paths']['log_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function run()
    {
        $this->log("========================================");
        $this->log("Email Sender Started");
        $this->log("========================================");
        
        // Load email list
        $emails = $this->loadEmailList();
        if (empty($emails)) {
            $this->log("ERROR: No emails found in list.txt");
            return ['success' => false, 'message' => 'No emails found'];
        }
        $this->log("Loaded " . count($emails) . " emails from list");
        
        // Load template
        $template = $this->loadTemplate();
        if ($template === false) {
            $this->log("ERROR: Template not found at " . $this->config['paths']['letter_file']);
            return ['success' => false, 'message' => 'Template not found'];
        }
        $this->log("Template loaded:  " . $this->config['paths']['letter_file']);
        
        // Cek multilang file
        $multilangFile = $this->config['paths']['multilang_file'];
        $useMultilang = file_exists($multilangFile);
        if ($useMultilang) {
            $this->log("Multilang file loaded: " . $multilangFile);
        }
        
        // Subject
        $emailSubject = $this->config['subject'];
        $this->log("Subject: " . $emailSubject);
        
        // Pengaturan sending
        $settings = $this->config['sending'];
        $emailsBeforeRest = $settings['emails_before_rest'];
        $restDuration = $settings['rest_duration'];
        $delayBetween = $settings['delay_between_emails'];
        $maxEmails = $settings['max_emails_per_session'];
        
        $this->log("Settings: {$emailsBeforeRest} emails before rest, {$restDuration}s rest duration");
        $this->log("Accounts available: " . count($this->accounts));
        $this->log("----------------------------------------");
        
        foreach ($emails as $index => $emailData) {
            if ($maxEmails > 0 && $this->emailsSent >= $maxEmails) {
                $this->log("Maximum emails per session reached ({$maxEmails})");
                break;
            }
            
            $email = $emailData['email'];
            $name = isset($emailData['name']) ? $emailData['name'] : null;
            
            $mailer = $this->getCurrentMailer();
            
            // Proses template dengan variabel
            $processedTemplate = $this->processTemplate($template, [
                'email' => $email,
                'name' => $name !== null ? $name :  '',
                'date' => date('Y-m-d'),
                'time' => date('H: i:s'),
                'link' => 'https://example.com/unsubscribe? email=' . urlencode($email)
            ]);
            
            // Proses multilang jika ada placeholder ##enc##
            if ($useMultilang && strpos($processedTemplate, '##enc##') !== false) {
                try {
                    $multilangWords = loadAndProcessMultilangData($multilangFile);
                    $processedTemplate = undetect(hideWordInHtml($processedTemplate, $multilangWords));
                } catch (Exception $e) {
                    $this->log("WARNING:  Multilang error - " . $e->getMessage());
                }
            }
            
            // Kirim email
            $result = $mailer->send($email, $emailSubject, $processedTemplate, null, $name);
            
            $this->emailsSent++;
            $this->emailsSentThisRest++;
            
            $fromName = isset($result['from_name']) ? $result['from_name'] : $mailer->getFromName();
            $fromEmail = $mailer->getFromEmail();
            
            if ($result['success']) {
                $this->totalSuccess++;
                $messageId = isset($result['data']['MessageID']) ? $result['data']['MessageID'] : 'N/A';
                $this->log("[SUCCESS] #{$this->emailsSent} -> {$email} (From: {$fromName} <{$fromEmail}>) ID: {$messageId}");
                $this->saveSentEmail($email);
            } else {
                $this->totalFailed++;
                $errorMsg = isset($result['data']['Message']) ? $result['data']['Message'] :  'Unknown error';
                $this->log("[FAILED] #{$this->emailsSent} -> {$email} - Error: {$errorMsg}");
                $this->saveFailedEmail($email, $errorMsg);
            }
            
            $this->rotateAccount();
            
            if ($this->emailsSentThisRest >= $emailsBeforeRest && $index < count($emails) - 1) {
                $this->log("----------------------------------------");
                $this->log("Resting for {$restDuration} seconds...");
                $this->log("Progress: {$this->emailsSent}/" . count($emails) . " | Success: {$this->totalSuccess} | Failed: {$this->totalFailed}");
                $this->log("----------------------------------------");
                sleep($restDuration);
                $this->emailsSentThisRest = 0;
            } else {
                if ($delayBetween > 0 && $index < count($emails) - 1) {
                    sleep($delayBetween);
                }
            }
        }
        
        $duration = time() - $this->startTime;
        $this->log("========================================");
        $this->log("Email Sending Completed!");
        $this->log("Total Sent: {$this->emailsSent}");
        $this->log("Success: {$this->totalSuccess}");
        $this->log("Failed: {$this->totalFailed}");
        $this->log("Duration: {$duration} seconds");
        $this->log("========================================");
        
        return [
            'success' => true,
            'total_sent' => $this->emailsSent,
            'total_success' => $this->totalSuccess,
            'total_failed' => $this->totalFailed,
            'duration' => $duration
        ];
    }

    private function loadEmailList()
    {
        $filePath = $this->config['paths']['email_list'];
        
        if (!file_exists($filePath)) {
            return [];
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $emails = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            if (strpos($line, ',') !== false) {
                $parts = explode(',', $line, 2);
                $email = trim($parts[0]);
                $name = isset($parts[1]) ? trim($parts[1]) : null;
            } else {
                $email = $line;
                $name = null;
            }
            
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = [
                    'email' => $email,
                    'name' => $name
                ];
            }
        }
        
        return $emails;
    }

    private function loadTemplate()
    {
        $templatePath = $this->config['paths']['letter_file'];
        
        if (!file_exists($templatePath)) {
            return false;
        }
        
        return file_get_contents($templatePath);
    }

    private function processTemplate($template, $variables)
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('##' . $key .  '##', $value, $template);
            $template = str_replace('## ' . $key .  ' ##', $value, $template);
            $template = str_replace('##' . $key . '##', $value, $template);
        }
        
        return $template;
    }

    private function getCurrentMailer()
    {
        $account = $this->accounts[$this->currentAccountIndex];
        
        return new PostmarkMailer(
            $account['api_token'],
            $account['from_email'],
            $account['from_name'],
            $this->config['api_url']
        );
    }

    private function rotateAccount()
    {
        if ($this->config['rotation_mode'] === 'random') {
            $this->currentAccountIndex = array_rand($this->accounts);
        } else {
            $this->currentAccountIndex++;
            if ($this->currentAccountIndex >= count($this->accounts)) {
                $this->currentAccountIndex = 0;
            }
        }
    }

    private function log($message)
    {
        $timestamp = date('Y-m-d H: i:s');
        $logMessage = "[{$timestamp}] {$message}";
        
        echo $logMessage . "\n";
        
        $logFile = $this->config['paths']['log_file'];
        file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
    }

    private function saveSentEmail($email)
    {
        $sentFile = $this->config['paths']['sent_file'];
        file_put_contents($sentFile, $email . "\n", FILE_APPEND);
    }

    private function saveFailedEmail($email, $error)
    {
        $failedFile = $this->config['paths']['failed_file'];
        $line = $email . '|' . $error .  '|' . date('Y-m-d H:i: s');
        file_put_contents($failedFile, $line . "\n", FILE_APPEND);
    }
}
