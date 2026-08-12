<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file stores credentials and configuration for third-party services.
    | Keep secrets in .env and never hard-code API credentials here.
    |
    */


    /*
    |--------------------------------------------------------------------------
    | Postmark
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Resend
    |--------------------------------------------------------------------------
    */

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Amazon SES
    |--------------------------------------------------------------------------
    */

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),

        'secret' => env('AWS_SECRET_ACCESS_KEY'),

        'region' => env(
            'AWS_DEFAULT_REGION',
            'us-east-1'
        ),
    ],


    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    */

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env(
                'SLACK_BOT_USER_OAUTH_TOKEN'
            ),

            'channel' => env(
                'SLACK_BOT_USER_DEFAULT_CHANNEL'
            ),
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | Generic SMS configuration used by eLive Events.
    |
    */

    'sms' => [
        'driver' => env(
            'SMS_DRIVER',
            'http'
        ),

        'provider' => env(
            'SMS_PROVIDER',
            'elive_sms'
        ),

        'api_url' => env(
            'SMS_API_URL'
        ),

        'api_key' => env(
            'SMS_API_KEY'
        ),

        'api_secret' => env(
            'SMS_API_SECRET'
        ),

        'sender_id' => env(
            'SMS_SENDER_ID',
            'eLive'
        ),

        'timeout' => (int) env(
            'SMS_TIMEOUT',
            30
        ),
    ],


    /*
    |--------------------------------------------------------------------------
    | eLive SMS Provider
    |--------------------------------------------------------------------------
    */

    'elive_sms' => [
        'base_url' => env(
            'ELIVE_SMS_BASE_URL'
        ),

        'api_key' => env(
            'ELIVE_SMS_API_KEY'
        ),

        'api_secret' => env(
            'ELIVE_SMS_API_SECRET'
        ),

        'delivery_report_path' => env(
            'ELIVE_SMS_DELIVERY_REPORT_PATH'
        ),

        'balance_path' => env(
            'ELIVE_SMS_BALANCE_PATH'
        ),

        'timeout' => (int) env(
            'ELIVE_SMS_TIMEOUT',
            30
        ),
    ],


    /*
    |--------------------------------------------------------------------------
    | SMS Balance API
    |--------------------------------------------------------------------------
    */

    'sms_balance' => [
        'url' => env(
            'SMS_BALANCE_URL'
        ),

        'method' => env(
            'SMS_BALANCE_METHOD',
            'get'
        ),

        'api_key' => env(
            'SMS_BALANCE_API_KEY'
        ),

        'api_secret' => env(
            'SMS_BALANCE_API_SECRET'
        ),

        'timeout' => (int) env(
            'SMS_BALANCE_TIMEOUT',
            30
        ),
    ],


    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Current WhatsApp use:
    |
    | - Automatic registration confirmation
    | - Digital badge delivery
    |
    */

    'whatsapp' => [

        /*
        |--------------------------------------------------------------------------
        | Provider
        |--------------------------------------------------------------------------
        */

        'provider' => env(
            'WHATSAPP_PROVIDER',
            'meta'
        ),


        /*
        |--------------------------------------------------------------------------
        | Meta Graph API
        |--------------------------------------------------------------------------
        */

        'graph_version' => env(
            'WHATSAPP_GRAPH_VERSION',
            'v24.0'
        ),


        /*
        |--------------------------------------------------------------------------
        | Meta Credentials
        |--------------------------------------------------------------------------
        */

        'access_token' => env(
            'WHATSAPP_ACCESS_TOKEN'
        ),

        'phone_number_id' => env(
            'WHATSAPP_PHONE_NUMBER_ID'
        ),

        'business_id' => env(
            'WHATSAPP_BUSINESS_ID'
        ),

        'app_id' => env(
            'WHATSAPP_APP_ID'
        ),

        'app_secret' => env(
            'WHATSAPP_APP_SECRET'
        ),


        /*
        |--------------------------------------------------------------------------
        | Webhook
        |--------------------------------------------------------------------------
        */

        'verify_token' => env(
            'WHATSAPP_VERIFY_TOKEN'
        ),


        /*
        |--------------------------------------------------------------------------
        | Request Settings
        |--------------------------------------------------------------------------
        */

        'timeout' => (int) env(
            'WHATSAPP_TIMEOUT',
            30
        ),


        /*
        |--------------------------------------------------------------------------
        | Default Language
        |--------------------------------------------------------------------------
        */

        'default_language' => env(
            'WHATSAPP_DEFAULT_LANGUAGE',
            'en'
        ),


        /*
        |--------------------------------------------------------------------------
        | WhatsApp Templates
        |--------------------------------------------------------------------------
        |
        | Currently only the registration confirmation template is enabled.
        |
        */

        'templates' => [

            'registration_confirmation' => env(
                'WHATSAPP_TEMPLATE_REGISTRATION_CONFIRMATION',
                'event_registration_confirmation'
            ),

        ],

    ],

];