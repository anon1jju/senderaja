<?php
/**
 * Class PostmarkMailer
 * Sender email menggunakan Postmark API
 * Compatible dengan PHP 7.4
 */

require_once 'functions.php';

class PostmarkMailer
{
    private $apiToken;
    private $apiUrl;
    private $fromEmail;
    private $fromName;

    public function __construct($apiToken, $fromEmail, $fromName, $apiUrl = 'https://api.postmarkapp.com/email')
    {
        $this->apiToken = $apiToken;
        $this->apiUrl = $apiUrl;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function send($toEmail, $subject, $htmlBody, $textBody = null, $toName = null)
    {
        // Proses placeholder di from_name
        $processedFromName = replacePlaceholder($this->fromName);
        
        $from = $processedFromName ?  "{$processedFromName} <{$this->fromEmail}>" : $this->fromEmail;
        $to = $toName ? "{$toName} <{$toEmail}>" : $toEmail;
        
        $payload = [
            'From' => $from,
            'To' => $to,
            'Subject' => $subject,
            'HtmlBody' => $htmlBody,
            'TextBody' => $textBody !== null ? $textBody : strip_tags($htmlBody),
            'MessageStream' => 'outbound'
        ];

        return $this->makeRequest($payload, $processedFromName);
    }

    public function getFromEmail()
    {
        return $this->fromEmail;
    }

    public function getFromName()
    {
        return $this->fromName;
    }

    private function makeRequest($payload, $processedFromName = '')
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Postmark-Server-Token: ' . $this->apiToken
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error,
                'http_code' => 0
            ];
        }

        $result = json_decode($response, true);

        return [
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'data' => $result,
            'from_name' => $processedFromName
        ];
    }
}
