<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.16. 17:49
 */

namespace PHPVisor\Internal\Server;

use PHPVisor\Internal\Options\Server\ProcessOptions;

class ProcessManager
{
    /**
     * @var array
     */
    protected $pool = array();

    /**
     * @var array
     */
    protected $runningPool = array();

    protected function addProcess(Process $process)
    {
        $this->pool[] = $process;
    }

    protected function addRunningProcess(Process $process)
    {
        $this->runningPool[$process->getPid()] = $process;
    }

    protected function removeProcessFromRunnings(Process $process)
    {
        unset($this->runningPool[$process->getPid()]);
    }

    protected function configure(array $processes)
    {
        foreach ($processes as $processConfig)
        {
            $this->addProcess(new Process(new ProcessOptions($processConfig)));
        }
    }

    private function logStartProcess(Process $process, Logger $logger)
    {
        if (!$process->getRunning())
        {
            $process->start();
            if ($process->isStarted())
            {
                if ($process->isRunning())
                {
                    $logger->notice("#".$process->getPid()." - ".$process->getName()." started.");
                } else {
                    $logger->warning("#".$process->getPid()." - ".$process->getName()." started, but not seem to be running.");
                }
            }
            else
            {
                $logger->error("Couldn't start process, proc_open has failed.  (name: ".$process->getName().").");
            }
        }
        else {
            $logger->warning("Want to start a process, what is already running. (pid: ".$process->getPid().", name: ".$process->getName().").");
        }
    }

    protected function startUp(Logger $logger)
    {
        foreach($this->pool as $process)
        {
            if ($process->startOnLaunch())
            {
                $this->startProcess($process, $logger);
            }
        }
    }

    protected function start(Logger $logger)
    {
        foreach($this->pool as $process)
        {
            $this->startProcess($process, $logger);
        }
    }

    protected function startProcess(Process $process, Logger $logger)
    {
        $this->logStartProcess($process, $logger);
        if ($process->isStarted())
        {
            $this->addRunningProcess($process);
        }
    }

    protected function stopProcess(Process $process, Logger $logger)
    {
        $this->removeProcessFromRunnings($process);
        $logger->notice("Shut down #".$process->getPid()." - ".$process->getName()." by user.");
        $process->stop();
    }

    protected function terminateProcess(Process $process, Logger $logger)
    {
        $this->removeProcessFromRunnings($process);
        $logger->notice("Terminate  #".$process->getPid()." - ".$process->getName()." by user.");
        $process->terminate();
    }

    protected function stopProcesses(Logger $logger)
    {
        foreach ($this->runningPool as $process)
        {
            $this->stopProcess($process, $logger);
        }
    }

    protected function terminateProcesses(Logger $logger)
    {
        foreach ($this->runningPool as $process)
        {
            $this->terminateProcess($process, $logger);
        }
    }

    private function isPidRunning(int $pid)
    {
        return isset($this->runningPool[$pid]);
    }

    protected function getRunningProcess($pid)
    {
        if ($this->isPidRunning($pid))
        {
            $process = $this->runningPool[$pid];
            if ($process->isRunning())
            {
                return $process;
            }
            return null;
        }
        return false;
    }

    protected function getRunningProcessByName($name)
    {
        foreach ($this->runningPool as $running)
        {
            if ($running->getName() == $name)
            {
                return $running;
            }
        }
        return null;
    }

    protected function getProcessesByGroupName(string $groupName)
    {
        $group = array();
        foreach ($this->pool as $process)
        {
            if (in_array($groupName, $process->getGroups()))
            {
                $group[] = $process;
            }
        }
        return $group;
    }

    protected function getProcessByName(string $name)
    {
        foreach ($this->pool as $process)
        {
            if ($process->getName() == $name)
            {
                return $process;
            }
        }
        return null;
    }
}