<?php

namespace App\Model;

use Nette;
use \App\Model\TokenManager;


/**
 * Class Token
 */
class Token
{
	use Nette\SmartObject;

	const
		PATH_TEMPLATES = '/domains/prednasky.com/processing/spokendata-submitter/TEMPLATES/BLOCKSET',
		PATH_EXPORT = '/domains/prednasky.com/processing/spokendata-submitter/DATA-EXPORT',
		PATH_WAIT = '/domains/prednasky.com/processing/spokendata-submitter/PROCESSES/005_submitter/A_WAIT'
	;

	/**
	 * @var array $values
	 * @var string $template
	 * @var integer $videoId
	 * @var float $created
	 * @var TokenManager $tokenManager
	 */
	private $values, $template, $videoId, $created, $tokenManager;

	/**
	 * Token constructor.
	 *
	 * @param      integer       $videoId       The video identifier
	 * @param      TokenManager  $tokenManager  The token manager
	 */
	public function __construct(int $videoId, TokenManager $tokenManager)
	{
		$this->videoId = $videoId;
		$this->tokenManager = $tokenManager;

		// Default values
		$this->values = [
			'callback_base_url' => 'http://prednasky.com/',
			'sge_priority' => 0
		];
	}

	public function setTemplate(string $template)
	{
		$this->template = $template.'.ini';

		if (is_readable(self::PATH_TEMPLATES.'/'.$this->template)) {
			\Tracy\Debugger::log("Token.php: Unable to set template '".$this->template."'", \Tracy\ILogger::ERROR);
			return TRUE;
		}
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
	 * @return     integer  Value of  $created
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
		$file = file_get_contents(self::PATH_TEMPLATES.'/'.$this->template);

		// Check if successfully loaded
		if ($file === FALSE) {
			return FALSE;
		}

		$this->created = microtime(true);
		$micro = sprintf("%03d",($this->created - floor($this->created)) * 1000);

		// Generate unique folder inside DATA-EXPORT
		$token_path= date('/Y/m/d/', $this->created);
		do {
			$unique_hash = bin2hex(random_bytes(16));
		} while (file_exists($token_path.$unique_hash));
		$token_path.= $unique_hash.'/';
		if (!isset($this->values['public_datadir'])) $this->values['public_datadir'] = $token_path;

		$token_path.= bin2hex(random_bytes(16));
		if (!isset($this->values['private_datadir'])) $this->values['private_datadir'] = $token_path.'/';

		$token_path = self::PATH_EXPORT.$token_path;

		if (!mkdir($token_path, 0373, TRUE)) {
			return FALSE;
		}

		// Set job_id (prioriry-date-time-subsec-userid-hash)
		$this->values['job_id'] = sprintf('%02d', $this->values['sge_priority']);
		$this->values['job_id'].= date('-Ymd-His-').$micro. '-';
		$this->values['job_id'].= sprintf('%06d', getmyuid()). '-';
		$idWithoutHash = $this->values['job_id'];

		// Insert token into database, if successful, token is unique
		while (TRUE) {
			$this->values['job_id'] = $idWithoutHash.bin2hex(random_bytes(4));
			try {
				$this->tokenManager->newToken($this);
				break;
			} catch (Nette\Database\UniqueConstraintViolationException $e) {}
		}
\Tracy\Debugger::barDump($this->values['job_id'], 'job_id');

		// Fill template with values
		if (!$this->fillTemplate($file, $token_path)) {
			rmdir($token_path);
			rmdir(self::PATH_EXPORT.$this->values['public_datadir']);
			return FALSE;
		}

		// Submit prepared token. Create token containg path to .ini file.
		return file_put_contents(self::PATH_WAIT.'/'.$this->values['job_id'], $token_path.'/config.ini', LOCK_EX);
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
		foreach ($this->values as $key => $value) {
			$file = str_replace('$PHP["'.$key.'"]', $value, $file);
		}

		$notFilled = [];
		$isFilled = preg_match_all('~\$PHP\["([a-z_]+)"\]~', $file, $notFilled);
		if ($isFilled !== 0) {
			\Tracy\Debugger::barDump($notFilled[1], 'notFilled[1]');
			foreach ($notFilled[1] as $value) {
				\Tracy\Debugger::log("Token.php: Unable to fill template '".$this->template."', missing '".$value."'", 'token');
			}
			return FALSE;
		}

		// Save config.ini to DATA-EXPORT
		if (file_put_contents($token_path.'/config.ini', $file, LOCK_EX) === FALSE) {
			return FASLE;
		}
		return TRUE;
	}

}
