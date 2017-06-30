<?php

namespace MultiOSDaemon;

use InvalidArgumentException;

/**
 * Service responsible for application daemons
 *
 * @package Daemon
 * @subpackage Service
 *
 * @author Valdas Petrulis <petrulis.valdas@gmail.com>
 */
class Service
{

	private $jobs = array();

	/**
	 * @return array[]
	 */
	public function getJobs()
	{
		return $this->jobs;
	}

	/**
	 * Add jobs item.
	 *
	 * @param array $job
	 *
	 * @return Service
	 */
	public function addJob($job)
	{
		if (empty( $job['name'] )) {
			throw new InvalidArgumentException('Job "name" must be given');
		}
		if (empty( $job['class'] )) {
			throw new InvalidArgumentException('Job "class" must be given');
		}
		$this->jobs[] = $job;

		return $this;
	}

	/**
	 * Sets jobs.
	 *
	 * @param array $jobs
	 *
	 * @return Service
	 */
	public function setJobs($jobs)
	{
		$this->jobs = array();
		array_walk($jobs, array( $this, 'addJob' ));

		return $this;
	}

	/**
	 * @param string $name
	 *
	 * @return array
	 */
	public function getJob($name)
	{
		foreach ( $this->getJobs() as $job ) {
			if ($job['name'] == $name) {
				return $job;
			}
		}

		return null;
	}

	/**
	 * @param string $name
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function getJobOrThrow($name)
	{
		$job = $this->getJob($name);
		if ( ! isset( $job )) {
			throw new InvalidArgumentException('Sir, such job does not registered: ' . $name);
		}

		return $job;
	}

	/**
	 * @param array $job Job configuration
	 *
	 * @return DaemonAbstract
	 */
	public function loadJob($job)
	{
		// Legacy projects which hates autoloaders
		if (isset($job['classFile'])) {
			$clazzFile = $job['classFile'];
			if (!file_exists($clazzFile)) {
				throw new InvalidArgumentException('Sir, job class does not exist: ' . $clazzFile);
			}
			require_once $clazzFile;
		}

		// Spawn Daemon
		$clazz = $job['class'];

		return new $clazz(
			$job['pidFile'],
			$job['statusFile'],
			$job['jobFile']
		);
	}

	/**
	 * @param string $name
	 *
	 * @return DaemonAbstract
	 */
	public function loadJobByName($name)
	{
		$job = $this->getJobOrThrow($name);

		return $this->loadJob($job);
	}


}
