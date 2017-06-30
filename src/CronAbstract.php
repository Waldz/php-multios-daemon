<?php

namespace MultiOSDaemon;

/**
 * Run any process as a CRON job - run only once in minute and do some job
 *
 * @package Daemon
 * @subpackage Model
 *
 * @author Valdas Petrulis <petrulis.valdas@gmail.com>
 */
abstract class CronAbstract
    extends DaemonAbstract
{

    /**
     * Daemon object constructor
     *
     * @param string $pidFile Daemon process id file
     * @param string $statusFile Daemon status file
     * @param string $logFile Daemon log file
     */
    public function __construct($pidFile, $statusFile, $logFile=null)
    {
        parent::__construct($pidFile, $statusFile, $logFile);

        // Run only once in a minute and exit
        $this->setCheckTtl(null);
        $this->setRestartTtl(0);

    }

    /**
     * Configures daemon job before looping
     *
     * @param array $jobParams
     * @return void
     */
    public function init($jobParams = array())
    {
        // Do nothing
    }

    /**
     * On loop cycle of daemon
     *
     * @return void
     */
    public function step()
    {
        if($this->checkJobTime()) {
            $this->doJob();
        } else {
            $this->log('CRON time not come yet');
        }
    }

    /**
     * Check if time has come for job to be run
     *
     * @return bool
     */
    public function checkJobTime()
    {
        return true;
    }

    /**
     * On time then CRON need to be run
     *
     * @return void
     */
    abstract public function doJob();

}
