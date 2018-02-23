<?php

namespace App\Model;

use Nette;
use \App\Model\TokenManager;
use \Tracy\Debugger;


/**
 * Class Token
 */
class Token
{
	use Nette\SmartObject;

	/**
	 * @var array $values
	 * @var string $template
	 * @var int $videoId
	 * @var \DateTime $created
	 * @var TokenManager $tokenManager
	 * @var array $parameters
	 * @var int $priority
	 */
	private $values, $template, $videoId, $created, $tokenManager, $parameters, $priority;


	/**
	 * Token constructor.
	 *
	 * @param  int  $videoId  The video identifier
	 * @param  TokenManager  $tokenManager  The token manager
	 * @param  aray  $parameters  Configuration of Nette framework
	 * @param  int  $priority  Priority of token, this is not SGE priority. Legacy, leave blank.
	 */
	public function __construct(int $videoId, TokenManager $tokenManager, array $parameters, int $priority=1)
	{
		$this->videoId = $videoId;
		$this->tokenManager = $tokenManager;
		$this->parameters = $parameters;
		$this->priority = $priority;

		$this->created = new \Datetime();

		// Default values
		$this->values = [
			'callback_base_url' => 'http://www.prednasky.com',
			'datadir_base_url' => 'http://prednasky.fit.vutbr.cz',
			'sge_priority' => 0
		];
	}


	/**
	 * Sets the template.
	 *
	 * @param      string  $template  Filename of template.
	 *
	 * @return     bool  Return TRUE when file exists and is readable, otherwise FALSE.
	 */
	public function setTemplate(string $template)
	{
		$this->template = $template.'.ini';

		if (is_readable($this->parameters['paths']['path_templates'].'/'.$this->template)) {
			return TRUE;
		}
		Debugger::log("Token.php: Unable to set template '".$this->parameters['paths']['path_templates'].'/'.$this->template."'", \Tracy\ILogger::ERROR);
		return FALSE;
	}


	/**
	 * Add new values to the existing ones. If already exist, ovewrite it.
	 *
	 * @param      array  $newValues  The new values
	 */
	public function setValues(array $newValues)
	{
		$this->values = array_merge($this->values, $newValues);
	}


	/**
	 * Get value of specific key.
	 *
	 * @param      string  $key    The key of value
	 *
	 * @return     mixed  The values.
	 */
	public function getValues(string $key)
	{
		return $this->values[$key];
	}


	/**
	 * Getter of the $videoIf.
	 *
	 * @return     integer  Value of $videoId
	 */
	public function getVideoId()
	{
		return $this->videoId;
	}


	/**
	 * Getter of the $created.
	 *
	 * @return     \DateTime  Value of  $created
	 */
	public function getCreated()
	{
		return $this->created;
	}


	/**
	 * Move and fill template .ini file, submit prepared token.
	 *
	 * @return     integer  Return FALSE if failed or positive number on succeed (of written bytes).
	 */
	public function submit()
	{
		// Load template
		$file = file_get_contents($this->parameters['paths']['path_templates'].'/'.$this->template);
		if ($file === FALSE) {
			Debugger::log("Token.php: Template '".$this->parameters['paths']['path_templates'].'/'.$this->template."' became unreadable.", \Tracy\ILogger::ERROR);
			return FALSE;
		}

		// Generate unique folder inside DATA-EXPORT
		$token_path = $this->created->format('/Y/m/d/');
		do {
			$unique_hash = bin2hex(random_bytes(16));
		} while (file_exists($token_path.$unique_hash));
		$token_path.= $unique_hash.'/';
		if (!isset($this->values['public_datadir'])) $this->values['public_datadir'] = $token_path;

		$token_path.= bin2hex(random_bytes(16));
		if (!isset($this->values['private_datadir'])) $this->values['private_datadir'] = $token_path.'/';

		$token_path = $this->parameters['paths']['path_export'].$token_path;

		if (!mkdir($token_path, 0373, TRUE)) {
			return FALSE;
		}

		// Set job_id (prioriry-date-time-subsec-01-userid-hash)
		$idWithoutHash =
			sprintf('%02d', $this->priority). '-'
			.$this->created->format('Ymd-His') . '-'
			.substr($this->created->format("u"), 0, 3). '-'
			.'01'. '-'
			.sprintf('%06d', getmyuid()). '-'
		;
		// Insert token into database, if successful, token is unique, if not, generate different hash
		while (TRUE) {
			$this->values['job_id'] = $idWithoutHash.bin2hex(random_bytes(4));
			try {
				$this->tokenManager->newToken($this);
				break;
			} catch (Nette\Database\UniqueConstraintViolationException $e) {}
		}
\Tracy\Debugger::barDump($this->values['job_id'], 'job_id');

		// Fill template with values, in case of failure, remove created directories.
		if (!$this->fillTemplate($file, $token_path)) {
			rmdir($token_path);
			rmdir($this->parameters['paths']['path_export'].$this->values['public_datadir']);
			return FALSE;
		}

		// Submit prepared token. Create token containg path to .ini file.
		return file_put_contents($this->parameters['paths']['path_wait'].'/'.$this->values['job_id'], $token_path.'/config.ini', LOCK_EX);
	}


	/**
	 * Fill template with values, save as new file to DATA-EXPORT
	 *
	 * @param      string  $file   Content of template file.
	 *
	 * @return     bool  TRUE if successful, otherwise FALSE
	 */
	private function fillTemplate(string $file, string $token_path)
	{
		// Fill template
		foreach ($this->values as $key => $value) {
			$file = str_replace('$PHP["'.$key.'"]', $value, $file);
		}

		// Check if everything was filled
		$notFilled = [];
		$isFilled = preg_match_all('~\$PHP\["([a-z_]+)"\]~', $file, $notFilled);
		if ($isFilled !== 0) {
\Tracy\Debugger::barDump($notFilled[1], 'notFilled[1]');
			foreach ($notFilled[1] as $value) {
				Debugger::log("Token.php: Unable to fill template '".$this->template."', missing '".$value."'", 'token');
			}
			return FALSE;
		}

		// Save config.ini to DATA-EXPORT
		if (file_put_contents($token_path.'/config.ini', $file, LOCK_EX) === FALSE) {
			Debugger::log("Token.php: Unable to fill template, unable to write into: '".$token_path."/config.ini'");
			return FASLE;
		}
		return TRUE;
	}

}
