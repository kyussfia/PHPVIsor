<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.16. 18:00
 */

namespace PHPVisor\Internal\Server;

use Socket\Raw\Socket;

class SocketManager extends \Socket\Raw\Factory
{
    /**
     * @var array
     */
    public $sockets = array();

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function buildSockets(array $socketConfigArray)
    {
        if (count($socketConfigArray) < 1)
        {
            throw new \RuntimeException("Cannot build any socket if it is not configured to build one, this would cause the server to be unreachable, when it's in daemon mode.");
        }

        foreach ($socketConfigArray as $key => $socketConfig)
        {
            if (!is_array($socketConfig))
            {
                throw new \InvalidArgumentException("Error on ".($key + 1).". socket config. Not an array. You must specify options in an array.");
            }
            $address = $this->parseAddress($socketConfig, $key);
            $socket = $this->createFromString($address, $scheme);
            //2nd check for implemented acceptions:
            if (!in_array($socket->getType(), array(SOCK_STREAM, SOCK_DGRAM)))
            {
                throw new \RuntimeException("PHPVisor Server currently supports SOCK_STREAM and SOCK_DGRAM type sockets only.");
            }

            $socket->setBlocking(false);
            $this->logger->debug($scheme. ' type socket on '.$address.' , is set to non-blocking');
            $options = $this->parseOptions($socketConfig, $key);

            if (isset($options['canReUseSocketAddress']))
            {
                $this->logger->debug('Socket on '.$address.' , is set to SO_REUSEADDR');
                $socket->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
            }

            $socket->bind($address);
            $this->addSocket($socket, $options['username'], $options['password']);
        }
    }

    private function parseOptions(array $socketConfig, $key) : array
    {
        $options = array();
        if (isset($socketConfig['canReUseSocketAddress']))
        {
            if (!is_bool($socketConfig['canReUseSocketAddress']))
            {
                throw new \InvalidArgumentException("Invalid value at canReUseSocketAddress (".($key + 1).". socket config). Allowed values: true, false.");
            }
            if ($socketConfig['canReUseSocketAddress'])
            {
                $options['canReUseSocketAddress'] = true;
            }
        }

        $options['username'] = null;
        if (isset($socketConfig['username']))
        {
            if (null !== $socketConfig['username'] && empty($socketConfig['username']))
            {
                throw new \InvalidArgumentException("Invalid value at username (".($key + 1).". socket config). Cannot be empty.");
            }
            if (null !== $socketConfig['username'] && strlen($socketConfig['username']) > 20)
            {
                throw new \InvalidArgumentException("Invalid value at username (".($key + 1).". socket config). Username length can't be greater than 20.");
            }
            $options['username'] = $socketConfig['username'];
        }

        $options['password'] = null;
        if (isset($socketConfig['password']))
        {
            if (null !== $socketConfig['password'] && empty($socketConfig['password']))
            {
                throw new \InvalidArgumentException("Invalid value at password (".($key + 1).". socket config). Cannot be empty.");
            }
            if (null !== $socketConfig['password'] && stream_is_local($socketConfig['password']) > 20)
            {
                throw new \InvalidArgumentException("Invalid value at password (".($key + 1).". socket config). Password length can't be greater than 20.");
            }
            $options['password'] = $socketConfig['password'];
        }
        return $options;
    }

    private function addSocket(Socket $socket, string $username = null, string $password = null)
    {
        if (array_key_exists($socket->getSockName(), $this->sockets))
        {
            throw new \RuntimeException("You can define only 1 socket on 1 given address.");
        }
        $this->sockets[$socket->getSockName()] = array(
            "socket" => $socket,
            "username" => $username,
            "password" => $password
        );
    }

    private function parseAddress(array $socketConfig, $key)
    {
        $allowedProtocols = array('tcp', 'udp', 'unix');
        if (!isset($socketConfig['protocol']) || empty($socketConfig['protocol']) || !in_array($socketConfig['protocol'], $allowedProtocols))
        {
            throw new \InvalidArgumentException("Invalid protocol option on ".($key + 1).". socket definition in config. Allowed values: ".implode(", ", $allowedProtocols));
        }
        $protocol = $socketConfig['protocol'];
        if (!isset($socketConfig['host']) || empty($socketConfig['host']))
        {
            throw new \InvalidArgumentException("Invalid host option on ".($key + 1).". socket definition in config.");
        }
        $host = $socketConfig['host'];
        if ($protocol != 'unix' && (!isset($socketConfig['port']) || empty($socketConfig['port'])))
        {
            throw new \InvalidArgumentException("Invalid port option on ".($key + 1).". socket definition in config. (Required if not use unix sockets)");
        }

        $port = "";
        if ($protocol == 'unix')
        {
            $this->logger->notice('Unix socket file deleted before create it again. Path: '.$host);
            @unlink($host);
        } else {
            $port = ":".$socketConfig['port'];
        }

        return $protocol . "://" . $host . $port;
    }

    public function openSockets()
    {
        foreach ($this->sockets as $socketData)
        {
            $socket = $socketData['socket'];
            if ($socket->getType() === SOCK_STREAM)
            {
                $socket->listen();
            }

            $this->logger->notice("Listening socket on: ". $socket->getSockName() . "\tType: " .$socket->getType());
        }
    }

    public function checkSockets()
    {
        //$this->logger->debug("Server started to check sockets.");
        foreach ($this->sockets as $socketData)
        {
            $socket = $socketData['socket'];
            $checkSocket = $socket->selectRead();
            if($checkSocket)
            {
                return $socketData;
            }
        }
        return null;
    }

    /**
     * Currently unused function
     *
     * Close all sockets, and log this into base logger.
     *
     * @param Logger $logger
     */
    public function closeSockets(Logger $logger)
    {
        foreach ($this->sockets as $socketData)
        {
            $socket = $socketData['socket'];
            $logger->notice("Close socket on: " . $socket->getSockName() . "\tType: " . $socket->getType());
            $socket->close();
        }
    }
}