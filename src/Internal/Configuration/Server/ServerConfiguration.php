<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.03. 19:06
 */

namespace PHPVisor\Internal\Configuration\Server;

use PHPVisor\Internal\Configuration\AbstractConfiguration;
use PHPVisor\Internal\Options\Server\ServerOptions;

class ServerConfiguration extends AbstractConfiguration
{
    public $sockets = array();

    public $processes = array();

    public function __construct(ServerOptions $options)
    {
        $this->options = $options;
        $this->loadFromFile($this->options->configurationFilePath,'json');
        $this->options->applyOptions();
    }

    public function getUmask()
    {
        return $this->options->umask;
    }

    public function getNoCleanup()
    {
        return $this->options->noCleanUp;
    }

    public function getLogOptions()
    {
        return $this->options->logOptions;
    }

    protected function loadFromJson(string $filePath)
    {
        $data = json_decode(file_get_contents($filePath), TRUE);

        if (!$data)
        {
            throw new \InvalidArgumentException("Not a valid JSON file to parse: " . $filePath);
        }

        $this->loadOptions($data);
        $this->loadLogOptions($data);
        $this->loadSockets($data);
        $this->loadProcesses($data);
    }

    private function loadProcesses(array $data)
    {
        if (!isset($data['processes']) || !is_array($data['processes'])) //only in file
        {
            throw new \InvalidArgumentException("Missing section processes at configuration file. It must be an array/list.");
        }
        $this->processes = $data['processes'];
    }

    private function loadSockets(array $data)
    {
        if (!isset($data['servers']) || !is_array($data['servers'])) //only in file
        {
            throw new \InvalidArgumentException("Missing section servers at configuration file. It must be an array/list.");
        }
        $this->sockets = $data['servers'];
    }

    private function loadOptions(array $data)
    {
        $configOptions = array(
            'noDaemon',
            'umask',
            'user',
            'directory',
            'pidFile',
            'noCleanUp'
        );
        $this->loadOptionsFromData($configOptions, $data, $this->options);
    }

    private function loadLogOptions(array $data)
    {
        $logOptionNames = array(
            'logFilePath',
            'logFileMaxBytes',
            'logMaxNumOfBackups',
            'logLevel',
            'logChildLogDir',
            'printLog'
        );
        $this->loadOptionsFromData($logOptionNames, $data, $this->options->logOptions);
    }
}