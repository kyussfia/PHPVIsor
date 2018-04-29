<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.06. 18:56
 */

namespace PHPVisor\Internal\Options\Server;

use PHPVisor\Internal\Options\AbstractOptions;

class ServerOptions extends AbstractOptions
{
    public $configurationFilePath = __DIR__ . "/../../../../app/config/server.cfg.json";

    public $noDaemon = false;

    public $user;

    public $umask = 022; //todo deamon

    public $directory = "/tmp/PHPVisor/daemon/"; //directory to demonize

    public $pidFile; // todo for deamon

    public $noCleanUp = true; // if noCleanUp true we wont clear temp data in folders

    /**
     * @var LogOptions
     */
    public $logOptions;

    public function needHelp()
    {
        return isset($this->options['h']) || isset($this->options['help']);
    }

    public function needVersion()
    {
        return isset($this->options['v']) || isset($this->options['version']);
    }

    public static function detectOptions()
    {
        $cmdOptions = self::getStartupOptions();
        $new = new self($cmdOptions);
        $new->setCfgFile();
        $new->user = posix_getpwuid(posix_geteuid())['uid'];
        $new->logOptions = LogOptions::detectOptions($cmdOptions);
        return $new;
    }

    public function applyOptions()
    {
        $this->setNoDaemon();
        $this->setUser();
        $this->setUmask();
        $this->setDirectory();
        $this->setPidFile();
        $this->setNoCleanUp();
        $this->logOptions->applyParentOptions();
    }


    private function setNoCleanUp()
    {
        $this->setBoolOptionIfExist('noCleanUp', 'k', 'nocleanup');
    }

    private function setPidFile() //todo validator? deamon? remove options if not needed
    {
        $this->setOptionIfExist('pidFile', array('j', 'pidfile'));
    }

    private function setDirectory()
    {
        $this->setOptionIfExist('directory', array('d', 'directory'));
        if (!is_dir($this->directory) && !mkdir($this->directory, 0777, true))
        {
            throw new \InvalidArgumentException("Can't create/find directory for daemon directory at: " . $this->directory);
        }
    }

    private function setUser()
    {
        $this->setOptionIfExist('user', array('u', 'user'));
        if (empty($this->user))
        {
            $this->user = posix_getpwuid(posix_geteuid())['name'];
        }
        if (!is_string($this->user) && !is_int($this->user))
        {
            throw new \InvalidArgumentException("Invalid option user. Allowed types are integer (uid), username (string). Default current user.");
        }
    }

    private function setUmask()
    {
        $this->setOptionIfExist('umask', array('m', 'umask'));
        if (!is_numeric($this->umask))
        {
            throw new \InvalidArgumentException("Invalid option at umask. Not a numeric value. (default: 022).");
        }
        $this->umask = (int)$this->umask;
        if (!is_int($this->umask))
        {
            throw new \InvalidArgumentException("Invalid option at umask. Mask integer required. (default: 022).");
        }
    }

    private function setNoDaemon()
    {
        $this->setBoolOptionIfExist('noDaemon', 'n', 'nodaemon');
    }

    private function setCfgFile()
    {
        $this->setOptionIfExist('configurationFilePath', array('c','configuration'));
        if (!is_file($this->configurationFilePath))
        {
            throw new \InvalidArgumentException('Invalid config file path: ' . $this->configurationFilePath);
        }
    }

    protected static function getStartupOptions()
    {
        return getopt(self::getShortOptions(), self::getLongOptions());
    }

    protected static function getShortOptions()
    {
        return "c:nhvu:m:d:l:y:z:e:j:q:kp";
    }

    protected static function getLongOptions()
    {
        return array(
            'configuration:',
            'nodaemon',
            'help',
            'version',
            'user:',
            'umask:',
            'directory:',
            'logfile:',
            'logfile_max_bytes:',
            'logfile_backups:',
            'loglevel:',
            'pidfile:',
            'childlogdir:',
            'nocleanup',
            'print_log'
        );
    }
}
