<?php

namespace MultiOSDaemon;

use InvalidArgumentException;
use UnexpectedValueException;
use COM;

/**
 * Run any process as a daemon - loop that has only one always running instance.
 * Loop is restarted time be time, but if loop proccess is still running new instance is not created.
 *
 * @package Daemon
 * @subpackage Model
 *
 * @author Valdas Petrulis <petrulis.valdas@gmail.com>
 */
abstract class DaemonAbstract
{

    const STATUS_RUNNING = 'run';
    const STATUS_FINISHED = 'finish';
    const STATUS_DEAD = 'dead';
    const STATUS_ENABLED = 'enabled';
    const STATUS_DISABLED = 'disabled';

    /**
     * Daemon process id file
     *
     * @var string
     */
    private $_pidFile;

    /**
     * Current process id of deamon
     * @var
     */
    private $_pid;

    /**
     * @var resource
     */
    private $_logger;

    /**
     * Daemon status file
     *
     * @var string
     */
    private $_statusFile;

    /**
     * Time (in seconds) after that deamon should be stopped
     * @var int
     */
    private $_restartTtl;

    /**
     * Time (in seconds) to pause after each check
     * @var int
     */
    private $_checkTtl = 1;

    /**
     * Daemon object constructor
     *
     * @param string $pidFile Daemon process id file
     * @param string $statusFile Daemon status file
     * @param string $logFile Daemon log file
     */
    public function __construct($pidFile, $statusFile, $logFile=null)
    {
        $this->setPidFile($pidFile);
        $this->setStatusFile($statusFile);
        if(isset($logFile)) {
            $this->setLogger(fopen($logFile, 'a'));
        }
    }

    public function __destruct()
    {
        if($logger=$this->getLogger()) {
            fclose($logger);
        }
    }

    /**
     * Set file where process id should be saved
     *
     * @param string $filename Path and name of a file
     *
     * @throws InvalidArgumentException
     */
    public function setPidFile($filename)
    {
        if( empty($filename) ) {
            throw new InvalidArgumentException('PID file not given');
        }
        $this->_pidFile = $filename;
    }

    /**
     * Set file where daemon status should be saved
     *
     * @param string $filename Path and name of a file
     *
     * @throws InvalidArgumentException
     */
    public function setStatusFile($filename)
    {
        if( empty($filename) ) {
            throw new InvalidArgumentException('Status file not given');
        }
        $this->_statusFile = $filename;
    }

    /**
     * @param resource $logger
     */
    public function setLogger($logger)
    {
        $this->_logger = $logger;
    }

    /**
     * @return resource
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * @param int $checkTtl
     */
    public function setCheckTtl($checkTtl)
    {
        $this->_checkTtl = $checkTtl;
    }

    /**
     * @return int
     */
    public function getCheckTtl()
    {
        return $this->_checkTtl;
    }

    /**
     * @param int $restartTtl
     */
    public function setRestartTtl($restartTtl)
    {
        $this->_restartTtl = $restartTtl;
    }

    /**
     * @return int
     */
    public function getRestartTtl()
    {
        return $this->_restartTtl;
    }

    /**
     * Write daemon status to file
     *
     * @param string $status Daemon status (run/stop)
     *
     * @throws InvalidArgumentException
     */
    public function setStatus($status)
    {
        if( $fh = fopen($this->_statusFile, "w")){
            fwrite($fh, $status);
            fclose($fh);
        }else{
            throw new InvalidArgumentException("Could not open file '{$this->_statusFile}' for writing");
        }
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        if( file_exists($this->_statusFile) ){
            return trim(file_get_contents($this->_statusFile));
        }else{
            return self::STATUS_DEAD;
        }
    }

    /**
     * @return bool
     */
    private function _isWindows()
    {
        if (substr(php_uname(), 0, 7) == "Windows") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Write daemon pid to file
     */
    private function _writePid()
    {
        if( $fh = @fopen($this->_pidFile, "w")){
            if($this->_isWindows()) {
                $pid = getmypid();
            } else {
                $pid = posix_getpid();
            }

            fwrite($fh, $pid);
            fclose($fh);
        }else{
            throw new InvalidArgumentException("Could not open file '{$this->_pidFile}' for writing");
        }
    }

    /**
     * Return PID of last process runned
     *
     * @return int
     * @throws UnexpectedValueException
     */
    public function getPid()
    {
        if(!isset($this->_pid)) {
            if( file_exists($this->_pidFile) ){
                $content = file_get_contents($this->_pidFile);
                if(preg_match('/^([0-9]+)$/', $content, $match)){
                    $this->_pid = $match[1];
                }
            }
        }
        return $this->_pid;
    }

    /**
     * Check if daemon PID is still active
     *
     * @return boolean
     */
    public function isPidRunning()
    {
        if($pid = $this->getPid()) {
            if($this->_isWindows()) {
                $res = shell_exec('tasklist /FI "PID eq '.$pid.'"');
                if(strpos($res, $pid)!==false) {
                    return true;
                } else {
                    return false;
                }

            } else {
                $res = shell_exec('ps h '.$pid);
                if(strpos($res, $pid)!==false) {
                    return true;
                } else {
                    return false;
                }
                /*if(posix_kill($pid, 0)){
                    return true;
                }else{
                    return false;
                }*/
            }
        }

        return false;
    }

    /**
     * Start main daemon loop
     */
    public function start($jobParams = array())
    {
        $dateStart = time();

        if(!$this->isPidRunning() && $this->getStatus()!=self::STATUS_DISABLED){
            $this->setStatus(self::STATUS_RUNNING);
            if($this->getStatus()==self::STATUS_RUNNING){
                $this->_writePid();
                $this->log(sprintf('Starting PID: %d', $this->getPid()));

                // Configure before looping
                $this->init($jobParams);

                while(true) {
                    // Loop cycle of daemon
                    $this->log('Loop..');
                    $this->step();

                    // Stop daemon at restart time
                    $restartTtl = $this->getRestartTtl();
                    if(isset($restartTtl)) {
                        if(time()>=$dateStart + $restartTtl - 5) {
                            $this->log('Daemon restarting');
                            break;
                        }
                    }

                    // Take some spare time after each cycle
                    $checkTtl = $this->getCheckTtl();
                    if(isset($checkTtl)) {
                        usleep($checkTtl*1000000);
                    }
                }

                $this->log(sprintf('Finishing PID: %d', $this->getPid()));
            }else{
                $this->log('Daemon already running');
            }
            $this->setStatus(self::STATUS_FINISHED);
        }else{
            $this->log('Daemon should not be running');
        }
    }

    /**
     * Stops daemon loop if it is currently running
     */
    public function stop()
    {
        if($this->isPidRunning()) {
            $pid = $this->getPid();
            $this->log(sprintf('Stopping PID: %d', $pid));

            if($this->_isWindows()) {
                $wmi = new COM("winmgmts:{impersonationLevel=impersonate}!\\\\.\\root\\cimv2");
                $processList = $wmi->ExecQuery("SELECT * FROM Win32_Process WHERE ProcessId='".$pid."'");
                foreach($processList as $process) {
                    $process->Terminate();
                }

            } else {
                exec('kill '.$pid);
                /*if(!posix_kill($pid, SIGKILL)){
                    throw new UnexpectedValueException('Failed to kill process with PID: '.$pid);
                }*/
            }
            if($this->isPidRunning()){
                throw new UnexpectedValueException('Failed to kill process with PID: '.$pid);
            }
        }
    }

    /**
     * Restart daemon
     */
    public function restart()
    {
        $this->stop();
        $this->start();
    }

    /**
     * Enable daemon
     */
    public function enable()
    {
        $this->setStatus(self::STATUS_ENABLED);
    }

    /**
     * Enable daemon
     */
    public function disable()
    {
        $this->setStatus(self::STATUS_DISABLED);
        $this->stop();
    }

    /**
     * Stops daemon loop if it is currently running
     *
     * @return string
     */
    public function status()
    {
        $pid = $this->getPid();
        if(empty($pid)) {
            return 'Not running (no PID)';
        }

        if($this->_isWindows()) {
            return shell_exec('tasklist /NH /FI "PID eq '.$pid.'"');

        } else {
            return shell_exec('ps h '.$pid);
        }
    }

    /**
     * @param string $message
     */
    public function log($message)
    {
        if($logger=$this->getLogger()) {
            list($timeUsec, $timeSec) = explode(" ", microtime());
            fwrite($logger, sprintf(
                '%s %01.6f [%s] %s'.PHP_EOL,
                date('Y-m-d H:i:s', $timeSec),
                $timeUsec,
                get_class($this),
                $message
            ));
        }
    }

    /**
     * Configures daemon job before looping
     *
     * @param array $jobParams
     * @return void
     */
    abstract public function init($jobParams = array());

    /**
     * On loop cycle of daemon
     *
     * @return void
     */
    abstract public function step();

}
