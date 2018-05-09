<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.10. 16:12
 */

namespace PHPVisor\Internal\Server;

use PHPVisor\Internal\Application;
use PHPVisor\Internal\Configuration\Server\ServerConfiguration;
use PHPVisor\Internal\Options\Server\ServerOptions;

class ServerApplication extends Application
{
    /**
     * @var ApplicationVersion
     */
    private $version;

    /**
     * @var Server
     */
    private $server;

    const HELP = "Options:" . PHP_EOL . PHP_EOL .
    " -c/--configuration FILENAME -- configuration file path, run the program with the given configuration file loaded". PHP_EOL .
    " -n/--nodaemon -- run in the foreground (same as 'nodaemon=true' in config file)" . PHP_EOL .
    " -h/--help -- print this usage message and exit" . PHP_EOL .
    " -v/--version -- print PHPVisor version number and exit" . PHP_EOL .
    " -u/--user USER -- run PHPVisor as this user (or numeric uid)" . PHP_EOL .
    " -m/--umask UMASK -- use this umask for daemon subprocess (default is 0)" . PHP_EOL .
    " -d/--directory DIRECTORY -- directory to chdir to when daemonized" . PHP_EOL .
    " -p/--print_log -- everything would write into log, also be printed to screen (only in nodaemon mode)" .PHP_EOL.
    " -l/--logfile FILENAME -- use FILENAME as logfile path" . PHP_EOL .
    " -y/--logfile_max_bytes BYTES -- use BYTES to limit the max size of logfile" . PHP_EOL .
    " -z/--logfile_backups NUM -- number of backups to keep when max bytes reached" . PHP_EOL .
    " -e/--loglevel LEVEL -- use LEVEL as log level (debug,info,warn,error,critical)" . PHP_EOL .
    " -j/--pidfile FILENAME -- write a pid file for the daemon process to FILENAME" . PHP_EOL .
    " -q/--childlogdir DIRECTORY -- the log directory for child process logs" . PHP_EOL .
    " -k/--nocleanup --  prevent the process from performing cleanup (removal of old automatic child log files) at startup." . PHP_EOL
    ;

    public function __construct()
    {
        $this->version = new ApplicationVersion();
        $this->options = ServerOptions::detectOptions();
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        if ($this->options->needHelp())
        {
            echo self::HELP . PHP_EOL;
            exit;
        }
        elseif ($this->options->needVersion())
        {
            $versionStr = $this->version->getFromFile(__DIR__ . "/../../../.version");
            if (null === $versionStr)
            {
                $versionStr = "UNKNOWN";
            }
            echo $this->version->prefix . $versionStr . PHP_EOL;
            exit;
        }

        $this->server = new Server(new ServerConfiguration($this->options));

        try{
            $this->server->init();
            $currentInfo = posix_getpwuid(posix_geteuid());

            if ($this->options->user !== $currentInfo['uid']) //We have to change user, because the current and the expected are not equal.
            {
                $this->changeUser();
            }

            $this->server->run();
        }
        catch (\Exception $e)
        {
            $this->server->logException($e);
            $this->server->logInfo("Server application's running has ended.");
            exit(1);
        }
    }

    private function changeUser()
    {
        $currentUid = posix_getpwuid(posix_geteuid())['uid'];

        $userInfo = !is_numeric($this->options->user) ? posix_getpwnam($this->options->user) : posix_getpwuid($this->options->user);

        $name = $userInfo['name'];
        $uid = $userInfo['uid'];
        $gid = $userInfo['gid'];

        if (null === $this->options->user || null === $uid || null === $gid)
        {
            throw new \InvalidArgumentException('Cannot identify user by : '. $this->options->user . ', username or uid expected.');
        }

        if ($uid == $currentUid)
        {
            $this->server->logInfo("On change user: The process already run as user " .  $name . " (#" . $uid . ")");
            return;
        }

        if ($currentUid != 0)
        {
            throw new \InvalidArgumentException('Cannot demote non-root user ' . $name. " (#". $uid ."), to ".  $this->options->user . '.');
        }

        if(!posix_setgid($gid))
        {
            throw new \RuntimeException('Cannot set group id (#'.$gid.')  for user: ' . $name . " (#" . $uid . ").");
        }

        $res = posix_setuid($uid);
        if(!$res)
        {
            throw new \RuntimeException('Cannot run as ' . $name . " (#" . $uid . ").");
        }

        $this->server->logInfo("On change user: Process user changed to " .  $name . " (#" . $uid . ")");
    }
}