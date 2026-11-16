<?php

/*
| Merged with Laravel's default logging config. Adds a "mail" channel so that
| MAIL_MAILER=log writes e-mails to storage/logs/mail.log even when
| LOG_LEVEL=error (set MAIL_LOG_CHANNEL=mail).
*/
return [
    'channels' => [
        'mail' => [
            'driver' => 'single',
            'path' => storage_path('logs/mail.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ],
    ],
];
