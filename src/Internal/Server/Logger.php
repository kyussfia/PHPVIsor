<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.10. 18:15
 */

namespace PHPVisor\Internal\Server;

use PHPVisor\Internal\Options\Server\LogOptions;

class Logger
{
    /**
     * @var string
     */
    private $dateFormat = "Y-m-d H:i:s";

    /**
     * @var mixed
     */
    private $logLevel;

    /**
     * @var int
     */
    private $maxLogFileSize;

    /**
     * @var bool|resource
     */
    private $handle;

    /**
     * @var string
     */
    private $file;

    /**
     * @var int
     */
    private $numOfBackups;

    /**
     * @var string
     */
    private $childDir;

    /**
     * @var string
     */
    private $mode = "ab";

    /**
     * @var bool
     */
    private $enabled = true;

    /**
     * @var bool
     */
    private $print;

    /**
     * @var int
     */
    private $logMode;

    const DEBUG = 1;
    const NOTICE = 2;
    const WARNING = 4;
    const ERROR = 8;

    private static function getLevels()
    {
        return array(
            strtoupper(self::levelToString(self::DEBUG)) => self::DEBUG,
            strtoupper(self::levelToString(self::NOTICE)) => self::NOTICE,
            strtoupper(self::levelToString(self::WARNING)) => self::WARNING,
            strtoupper(self::levelToString(self::ERROR)) => self::ERROR,
        );
    }

    public static function getLevelNames()
    {
        return array_keys(self::getLevels());
    }

    private static function levelToString($level)
    {
        switch ($level)
        {
            case self::DEBUG:
                return "DEBUG";
            case self::NOTICE:
                return "NOTICE";
            case self::WARNING:
                return "WARNING";
            case self::ERROR:
                return "ERROR";
        }
        return null;
    }

    public function __construct(LogOptions $options)
    {
        $levels = self::getLevels();
        $this->file = $options->logFilePath;
        $this->maxLogFileSize = $options->logFileMaxBytes;
        $this->numOfBackups = $options->logMaxNumOfBackups;
        $this->logLevel = $levels[strtoupper($options->logLevel)];
        $this->childDir = $options->logChildLogDir;
        $this->enabled = !empty($this->file) && $this->maxLogFileSize > 0;
        $this->print = $options->printLog;
        $this->logMode = $options->logMode;
        if ($this->enabled)
        {
            $this->handle = fopen($this->file, $this->mode);
        }
    }

    public function debug($entry, bool $pure = false)
    {
        $this->log($entry, self::DEBUG, $pure);
    }

    public function notice($entry, bool $pure = false)
    {
        $this->log($entry, self::NOTICE, $pure);
    }

    public function warning($entry, bool $pure = false)
    {
        $this->log($entry, self::WARNING, $pure);
    }

    public function error($entry, bool $pure = false)
    {
        $this->log($entry, self::ERROR, $pure);
    }

    protected function log($entry, $level = self::DEBUG, $pureData = false) {
        $pure = $this->logMode !== LogOptions::OUTMODE_PURE || $pureData;
        if ($pure)
        {
            if ($level >= $this->logLevel && !empty($entry))
            {
                $content = ($this->logMode === LogOptions::OUTMODE_PURE && $pureData) ? $entry : $this->levelToString($level).": [" . date($this->dateFormat) . "] ". $entry . "\n";
                if ($this->enabled)
                {
                    if (filesize($this->file) + strlen($entry) > $this->maxLogFileSize) {
                        $this->rotateFiles();
                    }

                    $this->writeLog($content);
                }
                if ($this->print)
                {
                    echo $content;
                }
            }
        }
    }

    public function cleanUp()
    {
        if (is_dir($this->childDir))
        {
            foreach (glob($this->childDir.DIRECTORY_SEPARATOR."*") as $file)
            {
                if(is_file($file))
                {
                    unlink($file);
                }
            }
        }
        $this->notice("Children log directory cleaned up.");
    }

    public function disable()
    {
        $this->enabled = false;
    }

    public function disablePrint()
    {
        $this->print = false;
    }

    private function writeLog($entry)
    {
        fwrite($this->handle, $entry);
    }

    private function rotateFiles()
    {
        fclose($this->handle);
        if (file_exists($this->file . "." . $this->numOfBackups)) {
            unlink($this->file . "." . $this->numOfBackups);
        }
        for ($i = $this->numOfBackups; $i > 0; $i--) {
            if (file_exists($this->file . "." . $i)) {
                rename($this->file . "." . $i, $this->file . "." . ($i + 1));
            }
        }
        rename($this->file, $this->file . ".1");
        $this->handle = fopen($this->file, $this->mode);
    }

    public function getCurrentHandle()
    {
        return $this->handle;
    }
}