<?php
/**
 * Randevu SaaS yapılandırması.
 * Bu dosyayı config.php olarak kopyala ve değerleri doldur.
 * config.php SAHİBİ www-data:www-data, MOD 640 OLMALI (yoksa site 500).
 */
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 5432,
        'dbname'   => 'randevu',
        'user'     => 'randevu',
        'password' => 'CHANGE_ME',
    ],
    'app' => [
        // Boş bırakılırsa URL'ler istekten (X-Forwarded-Proto + Host) türetilir.
        'base_url'      => '',
        'cookie_secure' => true,          // Cloudflare arkasında HTTPS
        'default_lang'  => 'tr',
        'remember_days' => 30,
        'secret'        => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
        // Test modu: tek host + /d/{slug} ile tenant çözümleme.
        'path_tenancy'  => true,
        // Prod subdomain modu için kök domain (demo.<root_domain>).
        'root_domain'   => 'berber.com',
    ],
    'notify' => [
        // stub | netgsm | iletimerkezi | twilio
        'driver' => 'stub',
        'from'   => 'DEMO',              // SMS başlık (gerçek sağlayıcıda onaylı olmalı)
        // Gerçek sağlayıcı anahtarları sonra:
        // 'netgsm' => ['user' => '', 'pass' => '', 'header' => ''],
        // 'twilio' => ['sid' => '', 'token' => '', 'whatsapp_from' => ''],
    ],
];
