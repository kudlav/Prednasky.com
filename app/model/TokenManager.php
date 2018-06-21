<?php
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Database\Context;
use Tracy\Debugger;

class TokenManager
{
	use Nette\SmartObject;

	const
		// Token table
		TABLE_TOKEN = 'token',
		TOKEN_ID = 'id',
		TOKEN_STATE = 'state',
		TOKEN_TYPE = 'type',
		TOKEN_PUBLIC_HASH = 'public_hash',
		TOKEN_PRIVATE_HASH = 'private_hash',
		TOKEN_CREATED = 'created',
		TOKEN_LAST_UPDATED = 'last_update',
		TOKEN_CURRENT_STATE = 'current_state',
		TOKEN_VIDEO = 'video',
		TOKEN_TEMPLATE = 'template',
		TOKEN_PENDING = 'pending_blocks',

		// Token_state table
		TABLE_STATE = 'token_state',
		STATE_ID = 'id',
		STATE_NAME = 'name',

		// Template table
		TABLE_TEMPLATE = 'template',
		TEMPLATE_ID = 'id',
		TEMPLATE_NAME = 'name',
		TEMPLATE_BLOCKS = 'blocks',
		TEMPLATE_DESCRIPTION = 'description',

		// State values
		STATE_SUBMITTED = 'submitted',
		STATE_START = 'start',
		STATE_INFO = "info",
		STATE_ERROR = "error",
		STATE_DONE = "done"
	;

	/**
	 * @var array $parameters
	 * @var Context
	 */
	private $parameters, $database;

	public function __construct(array $parameters, Context $database)
	{
		$this->parameters = $parameters;
		$this->database = $database;
	}

	/**
	 * Insert token into database.
	 *
	 * @param array $values
	 * @param ActiveRow $template
	 * @param \DateTime $created
	 * @param int $videoId
	 * @param int $state State of token according to `token_state` table.
	 * @param int $type Type of token according to `token_type` table.
	 * @return IRow|null Just inserted row, null in case of failure.
	 */
	public function newToken(array $values, ActiveRow $template, \DateTime $created, int $videoId, int $state=1, int $type=1): ?IRow
	{
		$public_hash = explode('/', $values['public_datadir']);
		$private_hash = explode('/', $values['private_datadir']);

		$row = $this->database->table(self::TABLE_TOKEN)->insert([
			self::TOKEN_ID => $values['job_id'],
			self::TOKEN_STATE => $state,
			self::TOKEN_TYPE => $type,
			self::TOKEN_TEMPLATE => $template->id,
			self::TOKEN_PENDING => $template->blocks,
			self::TOKEN_PUBLIC_HASH => $public_hash[4],
			self::TOKEN_PRIVATE_HASH => $private_hash[5],
			self::TOKEN_CREATED => $created,
			self::TOKEN_VIDEO => $videoId,
		]);

		return $row!==false ? $row : null;
	}

	/**
	 * Get tokens by video id.
	 *
	 * @param int $video_id The video identifier.
	 * @return Selection The tokens by video.
	 */
	public function getTokensByVideo(int $video_id): Selection
	{
		return $this->database->table(self::TABLE_TOKEN)->where(self::TOKEN_VIDEO, $video_id);
	}

	/**
	 * Get the token by identifier.
	 *
	 * @param string $job_id The job_id.
	 * @return ActiveRow The token with specified job_id or null when there is no such token.
	 */
	public function getTokenById(string $job_id): ?ActiveRow
	{
		$result = $this->database->table(self::TABLE_TOKEN)->get($job_id);
		return $result!==false ? $result : null;
	}

	/**
	 * Update token values.
	 *
	 * @param ActiveRow $row Token database row.
	 * @param array $values New values.
	 * @param VideoManager $videoManager.
	 * @return bool Success or error.
	 */
	public function updateToken(ActiveRow $row, array $values, VideoManager $videoManager): bool
	{
		$stateId = $this->database->table(self::TABLE_STATE)
			->where(self::STATE_NAME, $values['status'])
			->fetchField(self::STATE_ID)
		;

		if ($stateId === false) {
			Debugger::log("TokenManager: Tried to update token with unknown state '".$values['status']."'", \Tracy\ILogger::ERROR);
		}
		else {
			// Always update time of last update
			$data = [
				self::TOKEN_LAST_UPDATED => $values['datetime']
			];

			if ($row->state != $stateId) {
				// Update status if not INFO
				if ($values['status'] != self::STATE_INFO) {
					$data[self::TOKEN_STATE] = $stateId;
				}
				// Clear pending blocks when DONE
				if ($values['status'] == self::STATE_DONE) {
					$data[self::TOKEN_PENDING] = "";
					$data[self::TOKEN_CURRENT_STATE] = null;
				}
			}

			if ($values['status'] == self::STATE_INFO) {
				$data[self::TOKEN_CURRENT_STATE] = $values['block'];

				$pending = explode(';', $row->pending_blocks);
				if (isset($pending[$values['block']])) {
					unset($pending[$values['block']]);
				}
				$data[self::TOKEN_PENDING] = implode(';', $pending);

				// If info message delivers videolength
				$value = [];
				if (preg_match('~output_videolength=([\d\.]+)~', $values['message'], $value)) {
					var_dump(intval(round(floatval($value[1]))));
					$videoManager->setDuration((int) $row->video, (int) $value[1]);
				}
			}

			$success = $row->update($data);
			if ($success) return true;
		}

		return false;
	}

	/**
	 * @param int $id
	 * @param array $data
	 * @param string $code
	 * @return bool
	 */
	public function updateTemplate(int $id, array $data, string $code): bool
	{
		try {
			$this->database->table(self::TABLE_TEMPLATE)->get($id)->update($data);
		}
		catch (\PDOException $e) {
			return false;
		}

		if (file_put_contents($this->parameters['paths']['path_templates'] .'/'. $data['name'], $code) === false) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $name
	 * @return array
	 */
	public function getTemplateVariables(string $name): array
	{
		$vars = [];
		$file = file_get_contents($this->parameters['paths']['path_templates'] .'/'. $name);
		if ($file !== false) {
			preg_match_all('~\$PHP\["([a-z_]+)"\]~', $file, $vars);
			if (isset($vars[1])) {
				return $vars[1];
			}
		}

		return $vars;
	}

	/**
	 * @return array
	 */
	public function getTokenDefaults(): array
	{
		return [
			'sge_priority' => '0',
			'callback_base_url' => $this->parameters['paths']['callback_base_url'],
			'datadir_base_url' => $this->parameters['paths']['datadir_base_url'],
		];
	}

	/**
	 * @param ActiveRow $template
	 * @param array $values
	 * @param int $videoId
	 * @param int $priority
	 * @return string|null Return job ID or null when unsuccessfull.
	 */
	public function submitToken(ActiveRow $template, array $values, int $videoId, int $priority=1): ?string
	{
		// Load template
		$file = $this->getTemplateCode((string) $template->name);
		if ($file === false) {
			return null;
		}

		$created = new \Datetime();

		// Generate unique folder inside DATA-EXPORT
		$token_path = $created->format('/Y/m/d/');
		do {
			$unique_hash = bin2hex(random_bytes(16));
		} while (file_exists($token_path . $unique_hash));

		$token_path .= $unique_hash.'/';
		if (!isset($values['public_datadir'])) $values['public_datadir'] = $token_path;

		$token_path.= bin2hex(random_bytes(16));
		if (!isset($values['private_datadir'])) $values['private_datadir'] = $token_path .'/';

		$token_path = $this->parameters['paths']['path_export'] . $token_path;

		if (!mkdir($token_path, 0770, true)) {
			Debugger::log("TokenManager: Unable to create dir '". $token_path ."'", \Tracy\ILogger::ERROR);
			return null;
		}

		// Set job_id (prioriry-date-time-subsec-01-userid-hash)
		$idWithoutHash =
			sprintf('%02d', $priority). '-'
			.$created->format('Ymd-His') . '-'
			.substr($created->format("u"), 0, 3). '-'
			.'01'. '-'
			.sprintf('%06d', getmyuid()). '-'
		;
		// Insert token into database, if successful, token is unique, if not, generate different hash
		while (true) {
			$values['job_id'] = $idWithoutHash.bin2hex(random_bytes(4));
			try {
				if ($this->newToken($values, $template, $created, $videoId) === null) {
					return null;
				}
				break;
			} catch (Nette\Database\UniqueConstraintViolationException $e) {}
		}

		// Fill template with values, in case of failure, remove created directories.
		if (!$this->fillTemplate($values, $file, $token_path)) {
			Debugger::log("TokenManager: Unable to fill template '". $template->name ."'", \Tracy\ILogger::ERROR);
			rmdir($token_path); // Private datadir
			rmdir($this->parameters['paths']['path_export'] . $values['public_datadir']); // Public datadir
			return null;
		}

		// Submit prepared token. Create token containg path to .ini file.
		$result = file_put_contents($this->parameters['paths']['path_wait'] .'/'. $values['job_id'], $token_path .'/config.ini', LOCK_EX);
		return $result!==false ? $values['job_id'] : null;
	}

	/**
	 * Fill template with values, save as new file to DATA-EXPORT
	 *
	 * @param array $values
	 * @param string $file Content of template file
	 * @param string $token_path File-path of template
	 * @return bool true if successful, otherwise false
	 */
	private function fillTemplate(array $values, string $file, string $token_path): bool
	{
		// Fill template
		foreach ($values as $key => $value) {
			$file = str_replace('$PHP["'. $key .'"]', $value, $file);
		}

		// Check if everything was filled
		$notFilled = [];
		$isFilled = preg_match_all('~\$PHP\["([a-z_]+)"\]~', $file, $notFilled);
		if ($isFilled !== 0) {
			foreach ($notFilled[1] as $value) {
				Debugger::log("TokenManager: Unable to fill template, missing '". $value ."'", \Tracy\ILogger::ERROR);
			}
			return false;
		}

		// Save config.ini to DATA-EXPORT
		if (file_put_contents($token_path .'/config.ini', $file, LOCK_EX) === false) {
			Debugger::log("TokenManager: Unable to fill template, unable to write into: '". $token_path ."/config.ini'");
			return false;
		}

		return true;
	}

	/**
	 * Get the template by name.
	 *
	 * @param string $name Filename of template.
	 * @return ActiveRow|null ActiveRow or null if there is no such template.
	 */
	public function getTemplateByName(string $name=""): ?ActiveRow
	{
		$result = $this->database->table(self::TABLE_TEMPLATE)
			->where(self::TEMPLATE_NAME, $name)
			->fetch();
		return $result!==false ? $result : null;
	}

	/**
	 * Get the template by ID.
	 *
	 * @param int $id ID of template.
	 * @return ActiveRow|null ActiveRow or null if there is no such template.
	 */
	public function getTemplateById(int $id): ?ActiveRow
	{
		$result = $this->database->table(self::TABLE_TEMPLATE)->get($id);
		return $result!==false ? $result : null;
	}

	/**
	 * Get all templates
	 *
	 * @return Selection All rows in table `template`.
	 */
	public function getTemplates(): Selection
	{
		return $this->database->table(self::TABLE_TEMPLATE);
	}

	/**
	 * Get template code by template name.
	 *
	 * @param string $name Name of template
	 * @return null|string Code of template or null when error occurs.
	 */
	public function getTemplateCode(string $name): ?string
	{
		$content = file_get_contents($this->parameters['paths']['path_templates'] .'/'. $name);
		if ($content === false) {
			Debugger::log("TokenManager: Template '". $this->parameters['paths']['path_templates'] .'/'. $name ."' unreadable.", \Tracy\ILogger::ERROR);
			return null;
		}
		return $content;
	}
}
