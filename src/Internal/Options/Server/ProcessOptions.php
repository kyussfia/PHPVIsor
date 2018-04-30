<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.08. 11:03
 */

namespace PHPVisor\Internal\Options\Server;

use PHPVisor\Internal\Options\AbstractOptions;

class ProcessOptions extends AbstractOptions
{
    public $command; //runnable command
    public $autoStart = false; //start up on application start
    public $delay = 2; //wait this much time before firing the process
    public $autoRestartOn = self::NONE; //when restart the process
    public $exitCodes = array(0); //allowed exit codes when process exited
    public $stopWaitSecs = 5; //wait this much time if shut down process softly. (without signal);
    public $termSignal = SIGTERM; //if terminate by user, this signal will shot to the process
    public $customSignal = null; //user can shot a custom defined signal to the process
    public $termWaitSecs = 1; //wait this much time before terminate process with termSignal
    public $priority = 0; //relative priority
    public $groups = array(); //primitive groups of process
    public $name; //process name, if not given, itt will be "Process for [command]"
    public $envVariables = array(); //process environment variables to pass
    public $wdir; //working directory for process

    public $pushOutPutToParent = false; //if true process stdout will directed to parent This only for development
    public $stdErrorFile = null; //if null the stderr will directed to default log
    public $stdInFile = null; //if null there will be a pipe for process desc. else file path to read from

    const UNEXPECTED = 0;
    const EXPECTED = 1;
    const BOTH = 2;
    const NONE = 3;

    /**
     * @var LogOptions
     */
    public $logOptions;

    public function __construct(array $processOptions)
    {
        parent::__construct($processOptions);
        if (!isset($processOptions['command']) || empty($processOptions['command']))
        {
            throw new \InvalidArgumentException("Command options required to create a new process.");
        }
        $this->command = $processOptions['command'];
        $this->logOptions = LogOptions::detectOptions($processOptions);
        $this->setName();
        $this->setAutoStart();
        $this->setDelay();
        $this->setAutoRestartOn();
        $this->setExitCode();
        $this->setTermSignal();
        $this->setCustomSignal();
        $this->setStopSecs();
        $this->setTermSecs();
        //$this->setStdPushParent();
        $this->setStdErrorFile();
        $this->setStdInFile();
        $this->setPriority();
        $this->setGroups();
        $this->setEnvVariables();
        $this->setWDir();
    }

    private function setExitCode()
    {
        $this->setOptionIfExist('exitCodes', 'exitCodes');
        if (!is_array($this->exitCodes))
        {
            throw new \InvalidArgumentException("Invalid option at exitCodes. Allowed type array (default: [0]).");
        }
        foreach ($this->exitCodes as $exitCode)
        {
            if (!is_int($exitCode))
            {
                throw new \InvalidArgumentException("Invalid exitcode. Allowed type integer (e.g.: 0).");
            }
        }
    }

    private function setWDir()
    {
        $this->wdir = getcwd();
        $this->setOptionIfExist('wdir', 'directory');
        if (!is_dir($this->wdir))
        {
            throw new \InvalidArgumentException("Invalid option on wdir. Valid and existng directory-name needed for working directory.");
        }
    }

    private function setEnvVariables()
    {
        $this->setOptionIfExist('envVariables', 'envVariables');
        if (!is_array($this->envVariables))
        {
            throw new \InvalidArgumentException("Invalid option on envVariables. Expected type array of key => value, (default: []).");
        }
    }

    private function setStdErrorFile()
    {
        $this->setOptionIfExist('stdErrorFile', 'stdErrorFile');
    }

    private function setStdInFile()
    {
        $this->setOptionIfExist('stdInFile', 'stdInFile');
        if (!empty($this->stdInFile) && !is_file($this->stdInFile))
        {
            throw new \InvalidArgumentException("Invalid file at stdInFile.");
        }
    }

    private function setGroups()
    {
        $this->setOptionIfExist('groups', 'groups');
        if (!is_array($this->groups))
        {
            throw new \InvalidArgumentException("Invalid option at groups. Allowed type array (default: [\"myGroup\"]).");
        }
    }

    private function setPriority()
    {
        $this->setOptionIfExist('priority', 'priority');
        if (!is_int($this->priority) || $this->priority > 20 || $this->priority < -20)
        {
            throw new \InvalidArgumentException("Invalid option at priority. Allowed type integer [-20..20] less means higher. (default: 0).");
        }
    }

    private function setDelay()
    {
        $this->setOptionIfExist('delay', 'delay');
        if (!is_int($this->delay))
        {
            throw new \InvalidArgumentException("Invalid option at delay. Allowed type integer (default: 2).");
        }
    }

    private function setStopSecs()
    {
        $this->setOptionIfExist('stopWaitSecs', 'stopWaitSecs');
        if (!is_int($this->stopWaitSecs))
        {
            throw new \InvalidArgumentException("Invalid option at stopWaitSecs. Allowed type integer (default: 5).");
        }
    }

    private function setTermSecs()
    {
        $this->setOptionIfExist('termWaitSecs', 'termWaitSecs');
        if (!is_int($this->termWaitSecs))
        {
            throw new \InvalidArgumentException("Invalid option at termWaitSecs. Allowed type integer (default: 1).");
        }
    }

    private function setAutoStart()
    {
        $this->setOptionIfExist('autoStart', 'autoStart');
        if (!is_bool($this->autoStart))
        {
            throw new \InvalidArgumentException("Invalid option at autoStart. Allowed true, false. (default: false).");
        }
    }

    private function setName()
    {
        $this->setOptionIfExist('name', 'name');
        if (!$this->name)
        {
            $this->name = "Process for " . $this->command;
        }
    }

    private function setStdPushParent()
    {
        $this->setOptionIfExist('pushOutPutToParent', 'pushOutPutToParent');
        if (!is_bool($this->pushOutPutToParent))
        {
            throw new \InvalidArgumentException("Invalid option at stdOutPush. Allowed true, false. (default: false).");
        }
    }

    private function setTermSignal()
    {
        $this->setOptionIfExist('termSignal', 'termSignal');
        $this->termSignal = $this->resolveSignal($this->termSignal, 'termSignal');
    }

    private function setCustomSignal()
    {
        $this->setOptionIfExist('customSignal', 'customSignal');
        $this->customSignal = $this->resolveSignal($this->customSignal,'customSignal');
    }

    private function resolveSignal($value, string $targetName)
    {
        if (is_string($value))
        {
            $joker = "SIGNAME:";
            if (FALSE === strpos($value, $joker))
            {
                switch (strtoupper($value))
                {
                    case "CHLD":
                    case "SIGCHLD":
                        return SIGCHLD;
                    case "KILL":
                    case "SIGKILL":
                        return SIGKILL;
                    //case "CONT":
                    //case "SIGCONT":
                    //    return SIGCONT;
                    //case "STPT":
                    //case "SIGTSTP":
                    //    return SIGTSTP;
                    case "HUP":
                    case "SIGHUP":
                        return SIGHUP;
                    case "SYS":
                    case "SIGSYS":
                        return SIGSYS;
                    case "USR2":
                    case "SIGUSR2":
                        return SIGUSR2;
                    case "USR1":
                    case "SIGUSR1":
                        return SIGUSR1;
                    case "INT":
                    case "SIGINT":
                        return SIGHUP;
                    case "QUIT":
                    case "SIGQUIT":
                        return SIGQUIT;
                    case "TERM":
                    case "SIGTERM":
                        return SIGTERM;
                    default:
                        throw new \InvalidArgumentException("Invalid signal at ".$targetName
                            .". Allowed: (QUIT, TERM, HUP, SYS, USR2, USR1, INT, CHLD, KILL)");
                }
            }
            else {//if force signame want to be given for this, to reach all PHP signals.
                $signame = trim(str_replace($joker, '', $value));
                if (!defined($signame))
                {
                    throw new \InvalidArgumentException("Given constant not exists: ".$signame);
                }
                return constant($signame);
            }

        }
    }

    private function setAutoRestartOn()
    {
        $this->setOptionIfExist('autoRestartOn', 'autoRestartOn');
        if (is_string($this->autoRestartOn))
        {
            switch (strtoupper($this->autoRestartOn))
            {
                case "BOTH":
                    $this->autoRestartOn = self::BOTH;
                    break;
                case "EXPECTED":
                    $this->autoRestartOn = self::EXPECTED;
                    break;
                case "UNEXPECTED":
                    $this->autoRestartOn = self::UNEXPECTED;
                    break;
                case "NONE":
                    $this->autoRestartOn = self::NONE;
                    break;
                default:
                    throw new \InvalidArgumentException("Invalid option at autoRestartOn. Allowed: (BOTH, EXPECTED, UNEXPECTED, NONE)");
            }
        }
    }

}