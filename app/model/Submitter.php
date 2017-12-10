<?php

namespace App\Model;

use Nette;


/**
 * Managing tokens and templates.
 */
class Submitter extends Nette\SmartObject
{

	public function __construct()
	{
	}

	/**
	 * Get token object from template.
	 * 
	 * @param string $name Name of template.
	 * @return Token Prepared but not submitted token.
	 */
	public chooseTemplate(string $name)
	{
	}

	/**
	 * Fill token ini file with values.
	 *
	 * Move template.ini from SUBMITTER/TEMPLATES/BLOCKSET to SUBMITTER/DATA-EXPOERT/#hash#
	 *
	 * @param Token Fill ini file of this token.
	 */
	public prepare(Token $token)
	{
	}

	/**
	 * Submit token, processing will be started.
	 *
	 * Create token file with path to ini file inside folder SUMBITTER/PROCESSES/005_SUBMITTER/A_WAIT/#id_job#
	 *
	 * @param Token $token Token to submit.
	 */
	public submit(Token $token)
	{
	}

}
