<?php
declare(strict_types=1);

namespace App\Model;

use Nette;


/**
 * Provide access to parameters section of config.neon file
 */
class Parameters
{
	use Nette\SmartObject;

	private $parameters;


	public function __construct($parameters)
	{
		$this->parameters = $parameters;
	}

	/**
	 * Get array of parameter from config files.
	 *
	 * @return array Parameters section from config.local.neon and config.neon.
	 */
	public function getParam(): array
	{
		return $this->parameters;
	}
}
