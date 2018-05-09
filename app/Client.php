#!/usr/bin/php
<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.03. 16:31
 */

namespace PHPVisor;

include("../src/Autoloader.php");

Autoloader::register();

$client = new \PHPVisor\Internal\Client\ClientApplication();
$client->run();