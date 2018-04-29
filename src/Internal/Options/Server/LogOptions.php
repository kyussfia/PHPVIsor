<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.10. 20:49
 */

namespace PHPVisor\Internal\Options\Server;

use PHPVisor\Internal\Options\AbstractOptions;
use PHPVisor\Internal\Server\Logger;

class LogOptions extends AbstractOptions
{
    public $logFilePath = "/tmp/PHPVisor/log/server.log";

    public $logFileMaxBytes= 52428800;

    public $logMaxNumOfBackups = 5;

    public $logLevel = "DEBUG";

    public $logChildLogDir = '/tmp/PHPVisor/log/child';

    public $printLog = false;

    public $logMode = self::OUTMODE_NORMAL;

    const OUTMODE_NORMAL = 0;

    const OUTMODE_PURE = 1;

    private $defaultFields = array(
        'logChildLogDir' => array('q', 'childlogdir'),
        'logLevel' => array('e', 'loglevel'),
        'logMaxNumOfBackups' => array('z', 'logfile_backups'),
        'logFileMaxBytes' => array('y', 'logfile_max_bytes'),
        'logFilePath' => array('l', 'logfile'),
    );

    public static function detectOptions($options)
    {
        return new self($options);
    }

    public function applyChildOptions(string $name)
    {
        $this->logFilePath = null;
        $this->setLogFilePath(array('stdOutLogFilePath'));
        if (null !== $this->logFilePath) // in this case we have to log for children
        {
            if ("AUTO" === strtoupper($this->logFilePath))
            {
                $this->logFilePath = $this->logChildLogDir . DIRECTORY_SEPARATOR . preg_replace('/\s+|\//', '', $name) . ".log";
            }

            $this->setLogMode(array("logMode"));
            $this->setLogMaxNumOfBackups(array("stdOutLogMaxBackups"));
            $this->setLogFileMaxBytes(array("stdOutLogMaxBytes"));
            $this->setLogLevel(array("logLevel"));
        }//else no logging
    }

    public function applyParentOptions()
    {
        $this->setLogFilePath($this->defaultFields['logFilePath']);
        $this->setLogFileMaxBytes($this->defaultFields['logFileMaxBytes']);
        $this->setLogMaxNumOfBackups($this->defaultFields['logMaxNumOfBackups']);
        $this->setLogLevel($this->defaultFields['logLevel']);
        $this->setLogChildLogDir($this->defaultFields['logChildLogDir']);
        $this->setPrintLog();
    }


    private function setLogMode(array $options)
    {
        $this->setOptionIfExist('logMode', $options);
        if (is_string($this->logMode))
        {
            if (strtoupper($this->logMode) == "NORMAL")
            {
                $this->logMode = self::OUTMODE_NORMAL;
            }
            elseif (strtoupper($this->logMode) == "PURE")
            {
                $this->logMode = self::OUTMODE_PURE;
            }
        }
    }

    private function setLogChildLogDir(array $options)
    {
        $this->setOptionIfExist('logChildLogDir', $options);
        if ($this->logChildLogDir && !is_dir($this->logChildLogDir) && !mkdir($this->logChildLogDir, 0777, true))
        {
            throw new \InvalidArgumentException("Can't create/find directory for child log directory at: " . $this->logChildLogDir);
        }
    }

    private function setLogLevel(array $options)
    {
        $this->setOptionIfExist('logLevel', $options);
        if ($this->logLevel && !in_array(strtoupper($this->logLevel), Logger::getLevelNames()))
        {
            throw new \InvalidArgumentException("Invalid log level (".implode(',', Logger::getLevelNames())." allowed) : " . $this->logLevel ." given.");
        }
    }

    private function setLogMaxNumOfBackups(array $options)
    {
        $this->setOptionIfExist('logMaxNumOfBackups', $options);
        if ($this->logMaxNumOfBackups && !is_numeric($this->logMaxNumOfBackups))
        {
            throw new \InvalidArgumentException("Invalid number on logMaxNumOfBackups (0: no file backup if file size hit the limit): " . $this->logMaxNumOfBackups);
        }
    }

    private function setLogFileMaxBytes(array $options)
    {
        $this->setOptionIfExist('logFileMaxBytes', $options);
        $this->logFileMaxBytes = $this->convertSizeToInt($this->logFileMaxBytes);
        if (false === $this->logFileMaxBytes)
        {
            throw new \InvalidArgumentException("Invalid number on logFileMaxBytes (maxmimum filesize of lig file, default: 50MB (52428800)): " . $this->logFileMaxBytes);
        }
    }

    private function setLogFilePath(array $options)
    {
        $this->setOptionIfExist('logFilePath', $options);
    }

    private function setPrintLog()
    {
        $this->setBoolOptionIfExist('printLog', 'p', 'print_log');
    }
}