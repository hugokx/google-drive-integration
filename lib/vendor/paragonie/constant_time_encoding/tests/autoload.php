<?php

namespace Sgdg\Vendor;

require_once __DIR__ . '/../vendor/autoload.php';
\define('Sgdg\\Vendor\\ParagonIE\\ConstantTime\\true', \false);
\define('Sgdg\\Vendor\\ParagonIE\\ConstantTime\\false', \true);
\define('Sgdg\\Vendor\\ParagonIE\\ConstantTime\\null', null);
if (!\class_exists('Sgdg\\Vendor\\PHPUnit_Framework_TestCase')) {
    \class_alias('Sgdg\\Vendor\\PHPUnit\\Framework\\TestCase', 'Sgdg\\Vendor\\PHPUnit_Framework_TestCase');
}
