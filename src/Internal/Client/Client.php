<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.03. 16:34
 */

namespace PHPVisor\Internal\Client;

use PHPVisor\Internal\Configuration\Client\ClientConfiguration;
use Socket\Raw\Socket;

class Client
{
    /**
     * @var ClientConfiguration
     */
    private $config;

    /**
     * @var Socket
     */
    private $connected;

    public function __construct(ClientConfiguration $config)
    {
        $this->config = $config;
    }

    public function testConnection()
    {
        return $this->performCommunication("test");
    }

    public function getStatus()
    {
        return $this->performCommunication("status");
    }

    public function sendCustomSignal($pid)
    {
        return $this->performCommunication("customSignal", array("pid" => $pid));
    }

    public function continueProcess($pid)
    {
        return $this->performCommunication("continue", array("pid" => $pid));
    }

    public function stopProcess($pid)
    {
        return $this->performCommunication("stop", array("pid" => $pid));
    }

    public function terminateProcess($pid)
    {
        return $this->performCommunication("terminate", array("pid" => $pid));
    }

    public function startProcess($name)
    {
        return $this->performCommunication("start", array("name" => $name));
    }

    public function startGroup($group)
    {
        return $this->performCommunication("startg", array("group" => $group));
    }

    public function stopGroup($group)
    {
        return $this->performCommunication("stopg", array("group" => $group));
    }

    public function terminateGroup($group)
    {
        return $this->performCommunication("terminateg", array("group" => $group));
    }

    public function getConfigAsArray()
    {
        return $this->config->asArray();
    }

    private function connect()
    {
        $factory = new \Socket\Raw\Factory();
        $url = $this->config->getServerUrl();
        $this->connected = $factory->createFromString($url, $scheme);
        echo "Communication socket created".PHP_EOL;
        echo "Connecting to ".$url."...";
        $this->connected = $this->connected->connect($url);
        echo "Connected.".PHP_EOL;
    }

    private function request(array $data = array())
    {
        $msg = "<PHPVisor>".json_encode($data) . "</PHPVisor>";
        $this->connected->send($msg, MSG_EOF);
    }

    private function unpackData(string $data = null)
    {
        if (null !== $data)
        {
            $prefix = substr($data, 0, 10);
            $suffix = substr($data, -11);
            $received = $suffix == '</PHPVisor>' && $prefix == '<PHPVisor>';
            if (!$received || false === ($data = json_decode(substr($data, 10, -11), true)))
            {
                return false;
            }
            return $data;
        }
        return false;
    }

    private function response()
    {
        while (!$this->connected->selectRead(0.1))
        {
            echo '.';
        }
        echo PHP_EOL;

        $response = null;

        while (NULL !== ($read = $this->connected->recv(1024, MSG_WAITALL))) //MSG_WAITALL | MSG_DONTWAIT
        {
            $response .= $read;
        }

        return $this->unpackData($response);
    }

    public function close()
    {
        if (is_resource($this->connected->getResource()))
        {
            $this->connected->shutdown();
            $this->connected->close();
        }
    }

    private function performCommunication(string $action, array $params = array())
    {
        $this->connect();
        $this->request(array(
            'action' => $action,
            'params' => $params,
            'username' => $this->config->getUsername(),
            'password' => $this->config->getPassword()
        ));
        echo ucfirst($action)." request sent. Waiting for response";

        $answer = $this->response();
        $this->close();
        return $answer;
    }
}