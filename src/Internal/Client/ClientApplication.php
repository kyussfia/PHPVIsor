<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.23. 16:07
 */

namespace PHPVisor\Internal\Client;

use PHPVisor\Internal\Configuration\Client\ClientConfiguration;
use PHPVisor\Internal\Options\Client\ClientOptions;

class ClientApplication extends Application
{
    private $client;

    const HELP = "Options:" . PHP_EOL . PHP_EOL .
        " -c/--configuration FILENAME -- configuration file path, run the program with the given configuration file loaded". PHP_EOL .
        " -l/--url -- server uri, the client will connect for" . PHP_EOL .
        " -u/--user USER -- PHPVisor Client username for authentication when connect to server" . PHP_EOL .
        " -p/--pwd -- PHPVisor Client password for authentication when connect to server" . PHP_EOL .
        " -t/--prompt NAME -- The client prompt string, (default: PHPVisorClient)" . PHP_EOL.
        " -i/--history_file FILENAME -- Use this history file for readline, if available" . PHP_EOL.
        " -h/--help -- Print this help message and exit." . PHP_EOL
    ;

    public function __construct()
    {
        $this->options = ClientOptions::detectOptions();
    }

    public function run()
    {
        if ($this->options->needHelp())
        {
            echo self::HELP . PHP_EOL;
            exit;
        }

        $this->client = new Client(new ClientConfiguration($this->options));
        $this->main();
    }

    private function main()
    {
        while(1)
        {
            $this->printMenu();

            // Read user choice
            $choice = trim(fgets(STDIN));

            // Exit application
            if( $choice == 'q' || $choice == "quit" )
            {
                break;
            }
            // Act based on user choice
            $result = null;
            $skipServe = false;
            switch( $choice ) {
                case 'p':
                case 'print':
                    {
                        $result = array('success' => true, 'data' => array($this->client->getConfigAsArray()));
                        break;
                    }
                case 't':
                case 'test':
                    {
                        $result = $this->client->testConnection();
                        $result['data']['msg'] = "Connection status is OK.";
                        break;
                    }
                case 's':
                case 'status':
                    {
                        $result = $this->client->getStatus();
                        break;
                    }
                case 'c':
                case 'custom':
                    {
                        $pid = $this->readParamFromInput('pid');
                        $result = $this->client->sendCustomSignal($pid);
                        break;
                    }
                case 'u':
                case 'continue':
                    {
                        $pid = $this->readParamFromInput('pid');
                        $result = $this->client->continueProcess($pid);
                        break;
                    }
                case 'o':
                case 'stop':
                    {
                        $pid = $this->readParamFromInput('pid');
                        $result = $this->client->stopProcess($pid);
                        break;
                    }
                case 'r':
                case 'terminate':
                    {
                        $pid = $this->readParamFromInput('pid');
                        $result = $this->client->terminateProcess($pid);
                        break;
                    }
                case 'a':
                case 'start':
                    {
                        $name = $this->readParamFromInput('Process-name');
                        $result = $this->client->startProcess($name);
                        break;
                    }
                case 'g':
                case 'startgroup':
                    {
                        $name = $this->readParamFromInput('Group-name');
                        $result = $this->client->startGroup($name);
                        break;
                    }
                case 'd':
                case 'stopgroup':
                    {
                        $name = $this->readParamFromInput('Group-name');
                        $result = $this->client->stopGroup($name);
                        break;
                    }
                case 'e':
                case 'terminategroup':
                    {
                        $name = $this->readParamFromInput('Group-name');
                        $result = $this->client->terminateGroup($name);
                        break;
                    }
                default:
                    {
                        $skipServe = true;
                        echo "\n\n\e[1;33mNot a valid choice entered. Please provide a valid one.\e[0m\n\n";
                    }
            }
            if (!$skipServe)
            {
                if ($result)
                {
                    $this->serveResponse($result);
                    echo PHP_EOL;
                }
                else {
                    $this->printError("Cannot receive data from server properly.");
                    $this->client->close(); //we try to close if something wrong happened, if
                }
            }
        }
    }

    private function printMenu()
    {

        echo "****************** PHPVisor Client ******************\n";
        echo "p - print - Print out current Client configuration\n";
        echo "t - test - Test connection to the server\n";
        echo "s - status - Get PHPVisor Server Status\n";
        echo "c - custom - Send the preset customSignal to process  with the given pid\n";
        echo "u - continue - Continue paused (stopped by the system, but not terminated) process with the given pid\n";
        echo "o - stop - Stop process softly (wait for it's end) for the given pid.\n";
        echo "r - terminate - Stop process hardly (don't wait for it's end) for the given pid.\n";
        echo "a - start - Start process by its full(!) name.\n";
        echo "g - startgroup - Start processes by group name, if not all component can run, started ones will be auto-terminated.\n";
        echo "d - stopgroup - Stop processes by group name. (Soft stop)\n";
        echo "e - terminategroup - Terminate processes by group name (Hard stop)\n";
        echo "q - quit - Quit PHPVisor Client\n";
        echo "************* Enter command to perform **************\n";
    }

    private function readParamFromInput(string $targetParamName)
    {
        $choice = null;
        $valid = false;
        while (!$valid)
        {
            echo PHP_EOL."Please enter input parameter's value for ".$targetParamName.": ";
            $choice = trim(fgets(STDIN));
            $valid = !empty($choice);

            if (!$valid)
            {
                echo "\e[1;33mWarning\e[0m: Please enter a valid, nonempty value.".PHP_EOL;
            }
        }
        return $choice;
    }

    private function printAssocData(array $data, bool $header = true)
    {
        $mask = "";
        foreach (array_keys($data) as $key)
        {
            if ($key == "name")
            {
                $mask .= "%-30.30s\t";
            }
            elseif ($key == "lastStart" || $key == "lastStop")
            {
                $mask .= "%-23.23s\t";
            }
            elseif ($key == "groups")
            {
                $mask .= "%-13.13s\t";
            }
            elseif ($key == "stoppedByUser" || $key == "stoppedBySys")
            {
                $mask .= "%-11.13s\t";
            }
            elseif ($key == "signaled")
            {
                $mask .= "%-8.8s\t";
            }
            elseif (in_array($key, array("host:port", "username", "password")))
            {
                $mask .= "%-15.15s\t";
            }
            else {
                $mask .= "%-6.7s\t";
            }
        }
        $mask .= "\n";
        if ($header)
        {
            echo vsprintf($mask, array_keys($data));
        }

        foreach ($data as $key => &$line)
        {
            if (is_bool($line))
            {
                $line = $line ? "true" : "false";
            }
            if (null === $line)
            {
                $line = "NULL";
            }
            if (0 === $line)
            {
                $line = "0";
            }
            if (is_array($line))
            {
                $line  = json_encode($line);
            }
        }
        echo vsprintf($mask, $data);
    }

    private function printList(array $list)
    {
        $needHeader = true;
        foreach ($list as $item) {
            if ($needHeader)
            {
                $this->printAssocData($item);
                $needHeader = false;
            }
            else
            {
                $this->printAssocData($item, $needHeader);
            }
        }
    }

    private function printError(string $msg)
    {
        echo PHP_EOL."Status: \033[0;31mERROR\033[0m : ".$msg.PHP_EOL.PHP_EOL;
    }

    private function printSuccess(string $msg = null)
    {
        echo PHP_EOL."Status: \033[1;32mSUCCESS\033[0m";
        if (null !== $msg)
        {
            echo " : ".$msg;
        }
        echo PHP_EOL.PHP_EOL;
    }

    private function serveResponse(array $data)
    {
        if (isset($data['data']['error']))
        {
            $this->printError($data['data']['error']);
        }
        elseif (!$data['success'])
        {
            $this->printError($data['data']);
        }
        else {
            $this->printSuccess((isset($data['data']['msg']) ? $data['data']['msg'] : null));
            unset($data['data']['msg']);
            $this->printList($data['data']);
        }
    }
}