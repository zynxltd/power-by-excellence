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

    /*
    |--------------------------------------------------------------------------
    | Signup phone verification
    |--------------------------------------------------------------------------
    |
    | When null, phone verification is required only when a live SMS provider
    | (Twilio/Vonage) is configured. The "log" driver does not send real texts.
    | Set SIGNUP_PHONE_VERIFICATION=true|false to override auto-detection.
    |
    */

    'phone_verification_enabled' => env('SIGNUP_PHONE_VERIFICATION'),

    'email_verification_enabled' => env('SIGNUP_EMAIL_VERIFICATION', false),

    'address_verification_enabled' => env('SIGNUP_ADDRESS_VERIFICATION', false),

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
