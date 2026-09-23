<?php

return [
    // URL pública do site, usada por canonical, sitemap e dados estruturados.
    'site_url' => 'https://www.seudominio.com.br',
    'whatsapp_number' => '5532999999999',
    'store_email' => 'pedidos@seudominio.com.br',
    'from_email' => 'pedidos@seudominio.com.br',

    'smtp' => [
        'enabled' => true,
        'host' => 'smtp.seudominio.com.br',
        'port' => 587,
        'encryption' => 'tls', // tls, ssl ou vazio
        'auth' => true,
        'username' => 'pedidos@seudominio.com.br',
        'password' => 'SENHA_DO_EMAIL',
        'from_name' => 'Flora Camily',
    ],

    'db' => [
        'host' => 'localhost',
        'name' => 'NOME_DO_BANCO',
        'user' => 'USUARIO_DO_BANCO',
        'pass' => 'SENHA_DO_BANCO',
    ],
];
