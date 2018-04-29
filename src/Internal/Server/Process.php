<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.16. 16:22
 */

namespace PHPVisor\Internal\Server;


use PHPVisor\Internal\Options\Server\ProcessOptions;

class Process
{
    /**
     * @var ProcessOptions
     */
    protected $options;

    /**
     * @var int
     */
    protected $pid = 0;

    /**
     * @var bool if  process is started
     */
    protected $started = false;

    /**
     * @var bool if process is stopped
     */
    protected $stopped = false;

    /**
     * @var bool
     */
    protected $running = false;

    /**
     * @var bool
     */
    protected $signaled = false;

    /**
     * If process got a signal, store it here
     * @var array
     */
    protected $signals = array();

    /**
     * @var int error code
     */
    protected $errorCode = null;

    /**
     * @var string error message
     */
    protected $errorMsg = null;

    protected $status;

    protected $stoppedBySys = false;

    protected $stoppedByUser = false;

    protected $lastStart = null;

    protected $lastStop = null;

    protected $rounds = 0;

    protected $lastRunningResult = 0;

    protected $resource = null;

    protected $pipes = null;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(ProcessOptions $options)
    {
        $this->options = $options;
        $this->initDefaults();
        $this->options->logOptions->applyChildOptions($this->getName());
        $this->options->logOptions->printLog = $this->options->pushOutPutToParent;
        $this->logger = new Logger($this->options->logOptions);
    }

    private function initDefaults()
    {
        $this->pid = 0;
        $this->gid = 0;
        $this->errorCode = null;
        $this->errorMsg = null;
        $this->started = false;
        $this->running = false;
        $this->signaled = false;
        $this->stopped = false;
        $this->signals = array();
        $this->resource = null;
        $this->pipes = null;
    }

    private function getDescriptors()
    {
        $desc = array();
        //in
        $desc[0] = array("pipe", "r");
        //out in pipe anyway, handled as stream and can decide to get pure output via logMode setting
        if (!$this->options->pushOutPutToParent)//without pipe it poll outputto parent's out
        {
            $desc[1] = array("pipe", "w");
        }
        //stderr
        $desc[2] = null !== $this->options->stdErrorFile ? array("file", $this->options->stdErrorFile, 'a') : array("file", $this->options->logOptions->logFilePath, "a");
        return $desc;
    }

    public function start()
    {
        $this->logger->notice($this->getName()." starting.");
        $this->started = true;
        $this->lastStart = microtime();
        sleep($this->options->delay);
        $src = proc_open(escapeshellcmd($this->options->command), $this->getDescriptors(), $pipes, $this->options->wdir, $this->options->envVariables);

        if (false !== $src)
        {
            $this->resource = $src;
            $this->pipes = $pipes;
            stream_set_blocking($this->pipes[0], false);
            if (!$this->options->pushOutPutToParent)
            {
                stream_set_blocking($this->pipes[1], false);
            }
            $this->updateStatus();
            if (null !== $this->options->stdInFile)
            {
                $input = fopen($this->options->stdInFile, "r");
                if (false !== $input)
                {
                    $bytes = stream_copy_to_stream($input, $this->pipes[0]);
                    fclose($input);
                    $this->logger->debug($bytes . " bytes copied to the input of #".$this->getPid()." - ".$this->getName());
                }
                else {
                    $this->logger->error( "Can't open input file for #".$this->getPid()." - ".$this->getName());
                }

            }

            $this->logger->notice("#".$this->getPid()." - ".$this->getName()." started. (at working directory: ".$this->options->wdir.")");
            $this->setPriority();
        }
        else {
            $this->lastStop = microtime();
            $this->started = false;
            $this->logger->error("#".$this->getPid()." - ".$this->getName()." start failed");
        }
    }

    public function updateStatus()
    {
        $status = $this->getProcessStatus();
        if ($this->running && !$status['running'] && !$status['stopped']) //process exited right now
        {
            if ($status['exitcode'] != -1) //exited
            {
                $this->shutdown();
                $this->errorCode = $status['exitcode'];
                $this->errorMsg = pcntl_strerror($status['exitcode']);
            }
            if ($status['exitcode'] == 0) //exited normally
            {
                $this->logger->debug(($this->rounds + 1). ". running of ". $this->getName()." ended.");
                $this->rounds++;
            }
        }

        if (!$this->stopped && $status['stopped']) //stopped right now //stop can only arrive from
        {
            $this->lastStop = microtime();
            $this->logger->warning("#".$this->getPid()." - ".$this->getName()." has stopped.");
            $this->stopped = $status['stopped'];
            $this->addSignal($status['stopsig']);
        }
        $this->pid = $status['pid'];
        $this->running = $status['running'];

        if ($status['signaled']) //terminated or killed
        {
            $this->addSignal($status['termsig']);
        }

    }

    private function setPriority()
    {
        if ($this->options->priority !== 0) //dont change to default
        {
            if (posix_getpwuid(posix_geteuid())['uid'] === 0) //only super user can change priority
            {
                if (!pcntl_setpriority($this->options->priority, $this->pid, PRIO_PROCESS))
                {
                    $this->logger->warning("Cannot change process priority, pcntl_setpriority failed.");
                }
            }
            else {
                $this->logger->warning("Non-root user can't change priorities.");
            }
        }
    }

    public function restart()
    {
        $this->initDefaults();
        $this->start();
        $this->logger->notice("#".$this->getPid()." - ".$this->getName()." restarted.");
    }

    public function isRestartable()
    {
        $status = $this->getExitStatus();
        return false !== $status ? $this->options->autoRestartOn === $status || $this->options->autoRestartOn === ProcessOptions::BOTH : false;
    }

    private function getExitStatus()
    {
        if (!$this->running)
        {
            return $exitingStatus = $this->allowedStop($this->errorCode) ? ProcessOptions::EXPECTED : ProcessOptions::UNEXPECTED;
        }
        return false;
    }

    public function isNormalExit()
    {
        return $this->getExitStatus() == ProcessOptions::EXPECTED;
    }

    public function startOnLaunch()
    {
        return $this->options->autoStart;
    }

    public function isRunning()
    {
        if ($this->started)
        {
            $this->updateStatus();
        }
        return $this->running;
    }

    public function getRunning()
    {
        return $this->running;
    }

    public function isStarted()
    {
        return $this->started;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function getName()
    {
        return $this->options->name;
    }

    public function getSignals()
    {
        return $this->signals;
    }

    public function getSignaled()
    {
        return $this->signaled;
    }

    public function isTerminated()
    {
        return $this->signaled && count($this->signals) > 0;
    }

    public function isStopped()
    {
        return $this->stopped && (in_array(SIGTSTP, $this->signals) || in_array(SIGSTOP, $this->signals));
    }

    public function addSignal(int $signal, bool $user = false)
    {
        if ($signal == SIGCONT)
        {
            $this->stopped = false;
            $this->stoppedByUser = false;
            $this->stoppedBySys = false;
            $key = array_search(SIGSTOP, $this->signals);
            if ($key !== false)
            {
                unset($this->signals[$key]);
            }
            $key = array_search(SIGTSTP, $this->signals);
            if ($key !== false)
            {
                unset($this->signals[$key]);
            }
        }
        elseif (!in_array($signal, $this->signals))
        {
            $this->signaled = true;
            if ($user)
            {
                $this->stoppedByUser = true;
            }
            else {
                $this->stoppedBySys = true;
            }
            $this->signals[] = $signal;
        }
    }

    public function getStoppedBySys()
    {
        return $this->stoppedBySys;
    }

    public function getStoppedByUser()
    {
        return $this->stoppedByUser;
    }

    public function getLastStart()
    {
        return $this->lastStart;
    }

    public function getLastStop()
    {
        return $this->lastStop;
    }

    public function getCustomSignal()
    {
        return $this->options->customSignal;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return int
     */
    public function getRounds(): int
    {
        return $this->rounds;
    }

    public function getGroups()
    {
        return $this->options->groups;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    public function allowedStop($code)
    {
        return in_array($code, $this->options->exitCodes);
    }

    private function getProcessStatus($key = null)
    {
        $this->status = proc_get_status($this->resource);

        return null === $key ? $this->status : $this->status[$key];
    }

    public function stop()
    {
        $this->shutdown(true);
    }

    public function terminate()
    {
        $this->shutdown(true, true);
    }

    private function sendSignal($signal)
    {
        return posix_kill($this->pid, $signal);
    }

    public function sendCustomSignal()
    {
        $result = $this->sendSignal($this->getCustomSignal());
        if ($result)
        {
            $this->logger->notice("#".$this->getPid()." - ".$this->getName()." got a customSignal by user.");
            $this->addSignal($this->getCustomSignal());
        }
        return $result;
    }

    public function resume()
    {
        if ($this->isStopped())
        {
            $result = $this->sendSignal(SIGCONT);
            if ($result)
            {
                $this->logger->notice("#".$this->getPid()." - ".$this->getName()." resume manually by user.");
                $this->addSignal(SIGCONT);
            }
            return $result;
        }
        return null;
    }

    public function shutdown($direct = false, $force = false)
    {
        $str = $force ? "Terminate" : "Shut down";
        $this->logger->debug($str." #".$this->getPid()." - ".$this->getName().".");
        fclose($this->pipes[0]); //close process in
        if (!$this->options->pushOutPutToParent)
        {
            $this->logger->notice(stream_get_contents($this->pipes[1]), true);
            fclose($this->pipes[1]); //close process out
        }
        $this->lastStop = microtime();
        if ($force)
        {
            $this->stoppedBySys = false;
            $this->stoppedByUser = true;
            sleep($this->options->termWaitSecs);
            $this->signaled = proc_terminate($this->resource, $this->options->termSignal);
            $this->addSignal($this->options->termSignal);
        }
        elseif ($direct)
        {
            $this->stoppedBySys = false;
            $this->stoppedByUser = true;
            sleep($this->options->stopWaitSecs);
            $this->lastRunningResult = proc_close($this->resource);
        }
        else {
            $this->stoppedBySys = true;
            $this->stoppedByUser = false;
            proc_close($this->resource);
        }
        $this->running = false;
        $this->logger->notice("#".$this->getPid()." - ".$this->getName()." shutted down. Last running result: ".$this->lastRunningResult);
    }
}