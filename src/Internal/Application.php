<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.23. 16:09
 */

namespace PHPVisor\Internal;

use PHPVisor\Internal\Options\AbstractOptions;

abstract class Application
{
    /**
     * @var AbstractOptions
     */
    protected $options;

    /**
     * Run application
     *
     * @return void
     */
    abstract public function run();
}