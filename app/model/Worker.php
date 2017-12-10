<?php

namespace App\Model;

use Nette;


/**
 * Managing token output files
 */
class Worker extends Nette\SmartObject
{

	public function __construct()
	{
	}

	/**
	 * Return array of output files.
	 *
	 * Output files are stored in WORKER/DATA-EXPORT/#hash#
	 *
	 */
	public getOutputFiles(Token $token)
	{
	}

	/**
	 * Check state of token in database
	 *
	 * @return bool 1=finished, 0=running.
	 */
	public isFinished(Token $token)
	{
	}

}
