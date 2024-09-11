<?php

namespace Sgdg\Vendor;

// Don't redefine the functions if included multiple times.
if (!\function_exists('Sgdg\\Vendor\\GuzzleHttp\\Psr7\\str')) {
    require __DIR__ . '/functions.php';
}
