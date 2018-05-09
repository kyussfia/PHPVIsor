<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.23. 16:44
 */

namespace PHPVisor\Internal\Configuration\Client;

use PHPVisor\Internal\Configuration\AbstractConfiguration;
use PHPVisor\Internal\Options\Client\ClientOptions;

class ClientConfiguration extends AbstractConfiguration
{
    public function __construct(ClientOptions $options)
    {
        $this->options = $options;
        $this->loadFromFile($this->options->configurationFilePath, 'json');
        $this->options->applyOptions();
    }

    public function asArray()
    {
        return array(
            'host:port' => $this->options->serverUrl,
            'username' => $this->options->username,
            'password' => $this->options->password
        );
    }

    public function getServerUrl()
    {
        return $this->options->serverUrl;
    }

    public function getUsername()
    {
        return $this->options->username;
    }

    public function getPassword()
    {
        return $this->options->password;
    }

    protected function loadFromJson(string $filePath)
    {
        $data = json_decode(file_get_contents($filePath), TRUE);

        if (!$data)
        {
            throw new \InvalidArgumentException("Not a valid JSON file to parse: " . $filePath);
        }

        $this->loadOptions($data);
    }

    private function loadOptions(array $data)
    {
        $configOptions = array(
            'serverUrl',
            'username',
            'password',
            'prompt',
            'historyFile'
        );
        $this->loadOptionsFromData($configOptions, $data, $this->options);
    }
}