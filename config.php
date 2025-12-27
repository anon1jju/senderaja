<?php


// here is multi api and from mail, if you have one only, give the comment to other lines
return [
    'accounts' => [
        [
            'api_token' => '8bc277e5-f245-4043-b296-1d7f4d57fdd1',
            'from_email' => 'support@zendrop.com',
            'from_name' => '{left}T{right}A{left}&{left}T'
        ],
        /*[
            'api_token' => 'YOUR_POSTMARK_API_TOKEN_2',
            'from_email' => 'sender2@domain2.com',
            'from_name' => 'Admin {right} Team'
        ],
        [
            'api_token' => 'YOUR_POSTMARK_API_TOKEN_3',
            'from_email' => 'sender3@domain3.com',
            'from_name' => '{left}Official{right}'
        ],*/
    ],
    
    'rotation_mode' => 'sequential', // or 'random'
    'api_url' => 'https://api.postmarkapp.com/email',
    'subject' => 'Kefala Bafak Enteh', // here is your subject
    
    'sending' => [
        'emails_before_rest' => 3, // thread email
        'rest_duration' => 2, // break time in second
        'delay_between_emails' => 0, // speed time during send, 0 will be good.
        'max_emails_per_session' => 0,
    ],

    'paths' => [
        'email_list' => 'list.txt', // your list
        'letter_file' => 'letter/h1.html', // your letter
        'log_file' => 'logs/sender.log', // ignore
        'multilang_file' => 'multilang.json', // ignore
        'sent_file' => 'logs/sent.txt', // ignore
        'failed_file' => 'logs/failed. txt', // ignore
    ],
];
