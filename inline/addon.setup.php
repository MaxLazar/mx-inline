<?php

$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

if (!defined('MX_INLINE_NAME')) {
    define('MX_INLINE_NAME', $addonJson->name);
    define('MX_INLINE_VERSION', $addonJson->version);
    define('MX_INLINE_DOCS', '');
    define('MX_INLINE_DESCRIPTION', $addonJson->description);
    define('MX_INLINE_AUTHOR', 'Max Lazar');
    define('MX_INLINE_DEBUG', false);
}

return [
    'name' => $addonJson->name,
    'description' => $addonJson->description,
    'version' => $addonJson->version,
    'namespace' => $addonJson->namespace,
    'author' => 'Max Lazar',
    'author_url' => 'https://eecms.dev',
    'settings_exist' => false,
    // Advanced settings
    'services' => [],
];
