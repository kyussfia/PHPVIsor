<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.03. 16:33
 */

namespace PHPVisor\Internal\Server;

use PHPVisor\Internal\Configuration\Server\ServerConfiguration;

class Server extends ProcessManager
{
    /**
     * @var ServerConfiguration
     */
    private $config;

    /**
     * @var SocketManager
     */
    private $socketManager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Pid file handler of the daemon.
     *
     * @var resource|null
     */
    private $fh;

    public function __construct(ServerConfiguration $config)
    {
        $this->config = $config;
        $this->logger = new Logger($this->config->getLogOptions());
        $this->socketManager = new SocketManager($this->logger);
    }

    public function init()
    {
        if (!$this->config->getNoDaemon())
        {
            $this->openPid();
            $this->logger->disablePrint();
        }
        if (!$this->config->getNoCleanup())
        {
            $this->logger->cleanUp();
        }
        $this->logger->debug("Server application Initialized");
    }


    public function run()
    {
        if (!$this->config->getNoDaemon())
        {
            $this->daemonize();
        }
        $this->configure($this->config->processes);
        $this->startUp($this->logger); //have to start this here, this is a workaround, that PHP doesn't have the flag to avoid socket descriptors inharitance to the subprocesses.
        $this->socketManager->buildSockets($this->config->sockets);
        $this->socketManager->openSockets();
        //$this->startUp($this->logger);

        $this->runForever();
    }

    private function openPid()
    {
        $this->fh = fopen($this->config->getPidFile(), 'c+');
        if (false === $this->fh)
        {
            $this->logger->error("Can't create/open specified pidfile for daemon.");
            throw new \RuntimeException("Can't create/open specified pidfile for daemon.");
        }
        if (!flock($this->fh, LOCK_EX | LOCK_NB)) {
            $this->logger->error("Could not lock the pidfile. This daemon might already running.");
            throw new \RuntimeException("Could not lock the pidfile. This daemon might already running.");
        }
    }

    /**
     * The logprint option cause the logger tp echoing the log to the output, but we close it here.
     *  Solution is, when the child spawned, set print to false, no matter what.
     */
    private function daemonize()
    {
        //Forking
        $this->logger->debug("Start to make PHPVisor daemon.");
        $pid = pcntl_fork();
        if ($pid == -1) {
            $this->logger->error("Can't fork server into a daemon.");
            throw new \RuntimeException("Can't fork PHPVisor, pcntl_fork failed!");
        } else if ($pid) {
            //$pid //parent id
            $this->logger->notice("PHPVisor forked, exiting parent.");
            exit(0);
        } else {
            $this->logger->notice("Daemonizing PHPVisor - Server process.");
        }

        if (posix_setsid() === -1) {
            $this->logger->error("Can't make session leader the daemon process.");
            throw new \RuntimeException("Can't make session leader the daemon process.");
        }
        else {
            $this->logger->notice("Child process detached from terminal.");
        }

        if ($this->config->getDirectory())
        {
            $result = chdir($this->config->getDirectory());
            if ($result)
            {
                $this->logger->notice("Directory changed (chdir) to: " . $this->config->getDirectory());
            }
            else {
                $this->logger->error("Can't change directory (chdir) to: " . $this->config->getDirectory());
            }
        }

        fseek($this->fh, 0);
        ftruncate($this->fh, 0);
        fwrite($this->fh, getmypid());
        fflush($this->fh);
        $this->logger->notice("Pidfile for daemon process, wrote.");

        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);
        //redirect fd's if wanna use it
        $stdIn = fopen('/dev/null', 'r');
        $stdOut = fopen('/dev/null', 'w');
        $stdErr = fopen('/dev/null', 'w');

        $this->logger->notice("File descriptors resettled.");
        $this->useUmask();
        //if want to implement any signal forwarding, below this we can do this
    }

    private function useUmask()
    {
        $res = umask($this->config->getUmask());
        if ($res == $this->config->getUmask()) {
            $this->logger->notice($res . " umask used.");
        } else {
            $this->logger->warning("Cannot use mask: " . $this->config->getUmask());
        }
    }

    private function runForever()
    {
        while (1) {
            if ($socket = $this->socketManager->checkSockets()) {
                $this->acceptSocket($socket);
            }
            $this->checkProcesses();
        }
    }

    protected function checkProcesses()
    {
        //sleep(1);
        //$this->logger->debug("#" . getmypid() . " Parent started to check started processes.");
        foreach ($this->runningPool as $process) {

            $isRun = $process->isRunning();
            if ($isRun && !$process->isStopped()) {
                //$this->logger->debug("#" . $process->getPid() . " - " . $process->getName() . " is still running.");
            } elseif ($process->isStopped()) {
                $this->logger->warning("#" . $process->getPid() . " - " . $process->getName() . " is stopped by a signal. Signals got: " . implode(', ', $process->getSignals()));
            } elseif ($process->isTerminated()) {
                $this->logger->warning("#" . $process->getPid() . " - " . $process->getName() . " is terminated by a signal. Signals got: " . implode(', ', $process->getSignals()));
            } elseif ($process->isNormalExit()) {
                $this->logger->debug("#" . $process->getPid() . " - " . $process->getName() . " is normally exited, with code: " . $process->getErrorCode() . " and " . $process->getErrorMsg() . " status.");
            } else {
                $this->logger->error("#" . $process->getPid() . " - " . $process->getName() . " is abnormally exited in unhandled way, with code: " . $process->getErrorCode() . " and " . $process->getErrorMsg() . " status. " . (count($process->getSignals()) > 0 ? "Signals: " . implode(", ", $process->getSignals()) : ""));
            }

            if (!$isRun)
            {
                $this->removeProcessFromRunnings($process);
                if ($process->isRestartable())
                {
                    $process->restart();
                    if ($process->isStarted()) {
                        $this->logger->notice("#" . $process->getPid() . " - " . $process->getName() . " is restarted.");
                        $this->addRunningProcess($process);
                    } else {
                        $this->logger->error("Couldn't start process: " . $process->getName() . ". proc_open has failed.");
                    }
                }
            }
        }
    }

    public function logException(\Exception $e)
    {
        $this->logger->error("Exception raised with code: " . $e->getCode() . ", with the following message: " . $e->getMessage() . " Debug trace: " . $e->getTraceAsString());
    }

    public function logInfo(string $entry)
    {
        $this->logger->debug($entry);
    }

    /*************************Begin Client Interface *************************/

    /*GETTING*/

    private function getStatusResponse()
    {
        $response = array();
        foreach ($this->pool as $process)
        {
            $response[] = array(
                "pid" => array_key_exists($process->getPid(), $this->runningPool) ? $process->getPid() : NULL,
                "name" => $process->getName(),
                "running" => $process->getRunning(),
                "started" => $process->isStarted(),
                "stopped" => $process->isStopped(),
                "stoppedBySys" => $process->getStoppedBySys(),
                "stoppedByUser" => $process->getStoppedByUser(),
                "signaled" => $process->getSignaled(),
                "groups" => $process->getGroups(),
                "lastStart" => $this->formatMicrotime($process->getLastStart()),
                "lastStop" => $this->formatMicrotime($process->getLastStop()),
                "rounds" => $process->getRounds()
            );
        }
        return $response;
    }

    /*POSTING*/

    private function sendCustomSignal(array $params)
    {
        $pid = $this->validatePidParam($params);
        if (is_array($pid))
        {
            return $pid;
        }
        if (!($process = $this->getRunningProcess($pid)))
        {
            $this->logger->warning("Only running processes can be signaled. ".$pid." is not running.");
            return array("error" => "Only running processes can be signaled. ".$pid." is not running.");
        }
        if (null === $process->getCustomSignal())
        {
            $this->logger->warning("Can't send signal, because process's customSignal is not configured. (pid: ".$pid.").");
            return array("error" => "Can't send signal, because process's customSignal is not configured.");
        }

        if ($process->sendCustomSignal())
        {
            $this->logger->notice("Custom signal (".$process->getCustomSignal().") sent to the process: ".$pid);
            return array("msg" => "Custom signal (".$process->getCustomSignal().") sent to the process: ".$pid);
        }

        $this->logger->error("Failed to send signal to process: ".$pid);
        return array("error" => "Failed to send signal to process: ".$pid);
    }

    private function continueProcess(array $params)
    { //if stopped we can send continue signal it
        $pid = $this->validatePidParam($params);
        if (is_array($pid))
        {
            return $pid;
        }
        if (!($process = $this->getRunningProcess($pid)))
        {
            $this->logger->warning("Only running processes can be signaled. ".$pid." is not running.");
            return array("error" => "Only running processes can be signaled. ".$pid." is not running.");
        }

        if ($process->resume())
        {
            $this->logger->notice("#" . $process->getPid() . " - " . $process->getName() . " is resumed by user.");
            return array("msg" => "#" . $process->getPid() . " - " . $process->getName() . " is resumed by user.");
        }
        $this->logger->error("Failed to send SIGCONT signal to process: ".$pid);
        return array("error" => "Failed to send SIGCONT signal to process: ".$pid);
    }

    private function startProcessByName(array $params)
    {
        $name = $this->validateProcessName($params);
        if (!is_array($name))
        {
            $process = $this->getProcessByName($name);
            if (!$process->getRunning())
            {
                $this->startProcess($this->getProcessByName($name), $this->logger); //process existing with this name
                $process = $this->getRunningProcessByName($name);
                if ($process->getRunning()) {
                    $this->logger->notice("#" . $process->getPid() . " - " . $process->getName() . " is started by user.");
                    return array("msg" => "#" . $process->getPid() . " - " . $process->getName() . " is started by user.");
                } else {
                    $this->logger->error($process->getName() . " is started by user, but not running.");
                    return array("msg" => $process->getName() . " is started by user, but not running.");
                }
            }
            return array("msg" => $process->getName() . " is already running.");
        }
        return $name;
    }

    private function startProcessByGroupName(array $params)
    {
        $group = $this->validateGroupName($params);
        if (is_array($group))
        {
            return $group;
        }

        $processes = $this->getProcessesByGroupName($group);
        $error = false;
        $runningPids = array();
        $msg = null;
        $count = 0;
        for($i = 0; $i < count($processes) && !$error; $i++)
        {
            if (!$processes[$i]->getRunning())
            {
                $this->startProcess($processes[$i], $this->logger);
                $error = !$processes[$i]->getRunning();
                if ($error)
                {
                    $this->logger->warning("User start processes by groupname: ".$group.", but not all the processes running, so terminate all, which started now.");
                    $msg = $processes[$i]->getName() . " is started by user via group (".$group."), but not running. Revert action.";
                }
                else {
                    $count++;
                    $runningPids[] = $processes[$i]->getPid();
                }
            }
        }
        $total = count($processes);
        if (!$error)
        {
            $msg = "Processes of ".$group." started by user. (total:".($total).", already running: ".($total - $count).", started now: ".$count.")";
            $this->logger->debug($msg);
            return array("msg" => $msg);
        }
        else {
            foreach ($runningPids as $started)
            {
                $this->terminateProcess($started, $this->logger);
            }
            return array("error" => $msg);
        }
    }

    private function stopProcessesByGroupName(array $params)
    {
        $group = $this->validateGroupName($params);
        if (is_array($group))
        {
            return $group;
        }
        $processes = $this->getProcessesByGroupName($group);
        $count = 0;
        foreach ($processes as $process)
        {
            if ($process->getRunning())
            {
                $this->stopProcess($process, $this->logger);
                $count++;
            }
        }
        $total = count($processes);
        $this->logger->notice("Processes of the group ".$group." are stopped by user. (total:".$total.", already stopped: ".($total - $count).", stopped now: ".$count.")");
        return array("msg" => "Processes of the group ".$group." are stopped by user. (total:".$total.", already stopped: ".($total - $count).", stopped now: ".$count.")");
    }

    private function stopProcessByPid(array $params)
    {
        $pid = $this->validatePidParam($params);
        if (is_array($pid))
        {
            return $pid;
        }
        if (!($process = $this->getRunningProcess($pid)))
        {
            $this->logger->warning("Only running processes can be stopped. ".$pid." is not running.");
            return array("error" => "Only running processes can be stopped. ".$pid." is not running.");
        }
        $this->stopProcess($process, $this->logger);
        $this->logger->notice("#" . $process->getPid() . " - " . $process->getName() . " is stopped by user.");
        return array("msg" => "#" . $process->getPid() . " - " . $process->getName() . " is stopped by user.");
    }

    private function stopProcessByName(array $params)
    {
        $name = $this->validateProcessName($params);
        if (!is_array($name))
        {
            $process = $this->getProcessByName($name);
            if ($process->getRunning())
            {
                $this->stopProcess($process, $this->logger);
                $this->logger->notice("#" . $process->getPid() . " - " . $process->getName() . " is stopped by user.");
                return array("msg" => "#" . $process->getPid() . " - " . $process->getName() . " is stopped by user.");
            }
            return array("msg" => $process->getName() . " is not running.");
        }
        return $name;
    }

    private function terminateProcessesByGroupName(array $params)
    {
        $group = $this->validateGroupName($params);
        if (is_array($group))
        {
            return $group;
        }
        $processes = $this->getProcessesByGroupName($group);
        $count = 0;
        foreach ($processes as $process)
        {
            if ($process->getRunning())
            {
                $this->terminateProcess($process, $this->logger);
                $count++;
            }
        }
        $total = count($processes);
        $this->logger->notice("Processes of the group ".$group." are terminated by user. (total:".$total.", already terminated: ".($total - $count).", terminated now: ".$count.")");
        return array("msg" => "Processes of the group ".$group." are terminated by user. (total:".$total.", already terminated: ".($total - $count).", terminated now: ".$count.")");
    }

    private function terminateProcessByPid(array $params)
    {
        $pid = $this->validatePidParam($params);
        if (is_array($pid))
        {
            return $pid;
        }
        if (!($process = $this->getRunningProcess($pid)))
        {
            $this->logger->warning("Only running processes can be terminated. ".$pid." is not running.");
            return array("error" => "Only running processes can be terminated. ".$pid." is not running.");
        }
        $this->terminateProcess($process, $this->logger);
        $this->logger->notice("#" . $process->getPid() . " - " . $process->getName() . " is terminated by user.");
        return array("msg" => "#" . $process->getPid() . " - " . $process->getName() . " is terminated by user.");
    }

    private function terminateProcessByName(array $params)
    {
        $name = $this->validateProcessName($params);
        if (!is_array($name))
        {
            $process = $this->getProcessByName($name);
            if ($process->getRunning())
            {
                $this->terminateProcess($process, $this->logger);
                $this->logger->notice("#" . $process->getPid() . " - " . $process->getName() . " is terminated by user.");
                return array("msg" => "#" . $process->getPid() . " - " . $process->getName() . " is terminated by user.");
            }
            return array("msg" => $process->getName() . " is not running.");
        }
        return $name;
    }

    private function validatePidParam($params)
    {
        if (!isset($params['pid']) || empty($params['pid']))
        {
            return array("error" => "Missing param pid.");
        }

        if (!is_numeric($params['pid']))
        {
            return array("error" => "Pid must be a numeric value for process id.");
        }
        return (int)$params['pid'];
    }

    private function validateGroupName($params)
    {
        if (!isset($params['group']) || empty($params['group']))
        {
            return array("error" => "Missing param group.");
        }

        if (count($this->getProcessesByGroupName($params['group'])) <= 0)
        {
            return array("error" => "There is no group with the given name: ".$params['group']);
        }
        return $params['group'];
    }

    private function validateProcessName($params)
    {
        if (!isset($params['name']) || empty($params['name']))
        {
            return array("error" => "Missing param name.");
        }

        if (!$this->getProcessByName($params['name']))
        {
            return array("error" => "There is no process with the given name: ".$params['name']);
        }
        return $params['name'];
    }

    private function formatMicrotime($microtime)
    {
        if (null !== $microtime)
        {
            list($usec, $sec)  = explode(" ", $microtime);
            $usec = str_replace("0.", ".", $usec);
            return date('y-m-d H:i:s.', $sec) . round($usec, 3);
        }
    }

    /*************************End Client Interface *************************/

    /**************** Begin Socket handling *********************************/

    private function acceptSocket(array $socketData)
    {
        $socket = $socketData['socket'];
        if ($socket->getType() === SOCK_STREAM) {
            $this->acceptStreamSocket($socketData);
        } elseif ($socket->getType() === SOCK_DGRAM) {
            $this->acceptDgramSocket($socketData);
        }
    }

    private function acceptDgramSocket(array $socketData)
    {
        $socket = $socketData['socket'];
        $connClient = null;
        $this->logger->notice("Accepted UDP message on " . $socket->getSockName());
        $data = $socket->recvFrom(1024, MSG_DONTWAIT, $connClient);
        $response = $this->createResponse($data, $socketData, $connClient);
        $msg = $this->packData($response);
        $socket->sendTo($msg, MSG_EOF, $connClient);
        $this->logger->debug("Sent data to " . $connClient. " is: ".$msg);
        $this->logger->notice("Response UPD data sent to" . $connClient);
    }

    private function acceptStreamSocket(array $socketData)
    {
        $socket = $socketData['socket'];
        $conn = $socket->accept();
        $this->logger->notice("Accepted connection on " . $conn->getSockName() . " peer: " . $conn->getPeerName());
        $data = $conn->recv(1024, MSG_DONTWAIT); //get command
        $response = $this->createResponse($data, $socketData, $conn->getPeerName());
        $msg = $this->packData($response);
        $conn->send($msg, MSG_EOF);
        $this->logger->debug("Sent data to " . $conn->getPeerName(). " is: ".$msg);
        $this->logger->notice("Close connection of " . $conn->getPeerName());
        $conn->close();
    }

    /**************** End Socket handling *********************************/


    /**************** Begin Comm handling *********************************/

    private function resolveRequest(array $data)
    {
        if (!isset($data['action'])) {
            return array("error" => "Missing action.");
        }

        $missingParamResponse = array("error" => "Missing parameter(s) for action: ".$data['action']);

        $this->logger->debug("Resolve action: " . $data['action']);
        switch ($data['action']) {
            case 'test':
                return array();
                break;
            case 'status':
                return $this->getStatusResponse();
            case 'customSignal':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->sendCustomSignal($data['params']);
            case 'continue':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->continueProcess($data['params']);
            case 'stop':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->stopProcessByPid($data['params']);
            case 'stopn':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->stopProcessByName($data['params']);
            case 'terminate':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->terminateProcessByPid($data['params']);
            case 'terminaten':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->terminateProcessByName($data['params']);
            case 'start':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->startProcessByName($data['params']);
            case 'startg':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->startProcessByGroupName($data['params']);
            case 'stopg':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->stopProcessesByGroupName($data['params']);
            case 'terminateg':
                if (!isset($data['params']) || !is_array($data['params']))
                {
                    return $missingParamResponse;
                }
                return $this->terminateProcessesByGroupName($data['params']);
            default:
                return array("error" => "Unknown action.");
        }
    }

    private function createResponse(string $data, array $socketData, string $remote)
    {
        $realData = $this->unpackData($data);
        $response = array();
        $host = $socketData['socket'];

        if (false === $realData)
        {
            $response['success'] = false;
            $response['data']['error'] = "Server response: Failed to receive data.";
            $this->logger->error("Failed to handle received data at " . $host->getSockName() . ", from peer: " . $remote);
            $this->logger->debug("Received data: " . PHP_EOL . $data);
        } else {
            if ($this->authenticate($realData, $socketData))
            {
                $response['data'] = $this->resolveRequest($realData);
                $response['success'] = !isset($response['data']['error']);
            }
            else {
                $response['data'] = array("error" => "Access denied!");
                $response['success'] = false;
            }
        }
        return $response;
    }

    private function authenticate(array $requestData, array $socketData)
    {
        if (!empty($socketData['username']) || !empty($socketData['password']))
        {
            if (!empty($socketData['username']) && (!isset($requestData['username']) || $socketData['username'] != $requestData['username']))
            {
                return false;
            }

            if (!empty($socketData['password']) && (!isset($requestData['password']) || $socketData['password'] != $requestData['password']))
            {
                return false;
            }
        }
        return true;
    }

    private function unpackData(string $data)
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

    private function packData(array $data)
    {
        return "<PHPVisor>".json_encode($data) . "</PHPVisor>";
    }

    /**************** End Comm handling *********************************/

    //todo more client
    //todo: testing + options testing
}
