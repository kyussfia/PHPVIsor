<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.23. 16:15
 */

namespace PHPVisor\Internal\Options\Client;

use PHPVisor\Internal\Options\AbstractOptions;

class ClientOptions extends AbstractOptions
{
    public $configurationFilePath = __DIR__ . "/../../../../app/config/client.cfg.json";

    public $serverUrl = 'http://127.0.0.1:8080';

    public $username = null;

    public $password = null;

    public static function detectOptions()
    {
        $cmdOptions = self::getStartupOptions();
        $new = new self($cmdOptions);
        $new->setCfgFile();
        return $new;
    }

    public function applyOptions()
    {
        $this->setUrl();
        $this->setUsername();
        $this->setPwd();
    }

    public function needHelp()
    {
        return isset($this->options['h']) || isset($this->options['help']);
    }

    private function setCfgFile()
    {
        $this->setOptionIfExist('configurationFilePath', array('c','configuration'));
        if (!is_file($this->configurationFilePath))
        {
            throw new \InvalidArgumentException('Invalid config file path: ' . $this->configurationFilePath);
        }
    }

    private function setUrl()
    {
        $this->setOptionIfExist('serverUrl', array('l', 'url'));
    }

    private function setUsername()
    {
        $this->setOptionIfExist('username', array('u', 'user'));
        if (strlen($this->username) > 20)
        {
            throw new \InvalidArgumentException("The length of given username: ".$this->username." must be less than or equal to 20.");
        }
    }

    private function setPwd()
    {
        $this->setOptionIfExist('password', array('p', 'pwd'));
        if (strlen($this->username) > 20)
        {
            throw new \InvalidArgumentException("The length of given password: ".$this->password." must be less than or equal to 20.");
        }
    }

    protected static function getStartupOptions()
    {
        return getopt(self::getShortOptions(), self::getLongOptions());
    }

    protected static function getShortOptions()
    {
        return "c:l:u:p:h";
    }

    protected static function getLongOptions()
    {
        return array(
            'configuration:',
            'url:',
            'user:',
            'pwd:',
            'help'
        );
    }
}