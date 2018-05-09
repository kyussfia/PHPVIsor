<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.03. 16:29
 */

namespace PHPVisor;

/**
 * Simple autoloader
 */
class Autoloader
{
    private static function isExternal($path)
    {
        return strpos($path, 'Raw') !== false;
    }

    public static function register()
    {
        spl_autoload_register(function ($class) {
            $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
            if (strpos($path, 'Internal') !== false)
            {
                $path = self::resolveInternal($path);
            }
            elseif (self::isExternal($path))
            {
                $path = self::resolveExternal($path, 2);
            }

            //echo $path .PHP_EOL;

            if (is_dir($path))
            {
                return self::registerFolder($path);
            } else {
                return self::requireFile($path . '.php');
            }
        });
    }

    private static function resolveInternal($path)
    {
        return __DIR__ . DIRECTORY_SEPARATOR . str_replace('PHPVisor/', '', $path);
    }

    private static function resolveExternal($path, $subDirIndex, $subDir = 'src')
    {
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        array_splice($parts, $subDirIndex, 0, $subDir);
        return __DIR__ . DIRECTORY_SEPARATOR . 'External' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private static function registerFolder($dir)
    {
        foreach (scandir($dir) as $filename) {
            $path = $dir . '/' . $filename;
            if (!self::requireFile($path))
            {
                return false;
            }
        }
        return true;
    }

    private static function requireFile($file)
    {
        if (is_file($file) && file_exists($file))
        {
            require $file;
            return true;
        }
        return false;
    }
}