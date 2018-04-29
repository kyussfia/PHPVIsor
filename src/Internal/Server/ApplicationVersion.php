<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.14. 20:23
 */

namespace PHPVisor\Internal\Server;

class ApplicationVersion
{
    public $prefix = "Version ";

    public function getFromFile($path)
    {
        if (!($file = @fopen($path, "r")) || ($line = fgets($file)) === false)
        {
            return null;
        }

        return trim(str_replace('\n', '', $line));
    }
}