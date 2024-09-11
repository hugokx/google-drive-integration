<?php

namespace Sgdg\Vendor;

// Don't redefine the functions if included multiple times.
if (!\function_exists('Sgdg\\Vendor\\GuzzleHttp\\uri_template')) {
    require __DIR__ . '/functions.php';
}
