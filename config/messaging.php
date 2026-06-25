<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default messaging providers
    |--------------------------------------------------------------------------
    |
    | sms: log | twilio | vonage
    | email: smtp | sendgrid | mailgun | postmark | resend
    |
    */

    'sms_provider' => env('MESSAGING_SMS_PROVIDER', 'log'),

    'email_provider' => env('MESSAGING_EMAIL_PROVIDER', 'smtp'),

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    'vonage' => [
        'key' => env('VONAGE_API_KEY'),
        'secret' => env('VONAGE_API_SECRET'),
        'from' => env('VONAGE_FROM'),
    ],

    'sendgrid' => [
        'key' => env('SENDGRID_API_KEY'),
    ],

];
