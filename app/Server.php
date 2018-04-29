#!/usr/bin/php
<?php

/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.03. 16:31
 */

namespace PHPVisor;

use PHPVisor\Internal\Server\ServerApplication;

$allowedOs = array("Linux", "Unix");
if (in_array(PHP_OS, $allowedOs))
{
    include(__DIR__ . "/../src/Autoloader.php");

    error_reporting(E_ALL);

    Autoloader::register();

    $app = new ServerApplication();
    $app->run();
}
else {
    echo "PHPVisor - Server component is only supported by the following systems: ".implode(", ", $allowedOs);
}
