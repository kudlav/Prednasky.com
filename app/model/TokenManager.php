<?php

namespace App\Model;

use Nette;
use Nette\Database;

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
		TOKEN_LAST_UDPATED = 'last_update',
		TOKEN_VIDEO = 'video',

		// Token_state table
		TABLE_STATE = 'token_state',
		STATE_ID = 'id',
		STATE_NAME = 'name',

		// State values
		STATE_SUBMITTED = 'submitted',
		STATE_START = 'start',
		STATE_ERROR = "error",
		STATE_DONE = "done"
	;

	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Database\Context $database)
	{
		$this->database = $database;
	}

	/**
	 * Insert token into database
	 *
	 * @param      Token    $token  The token
	 * @param      integer  $state  State of token according to `token_state` table
	 * @param      integer  $type   Type of token according to `token_type` table
	 *
	 * @return     Database\IRow  Just insertet row
	 */
	public function newToken(Token $token, int $state=1, int $type=1)
	{
		$public_hash = explode('/', $token->getValues('public_datadir'));
		$private_hash = explode('/', $token->getValues('private_datadir'));

		$row = $this->database->table(self::TABLE_TOKEN)->insert([
			self::TOKEN_ID => $token->getValues('job_id'),
			self::TOKEN_STATE => $state,
			self::TOKEN_TYPE => $type,
			self::TOKEN_PUBLIC_HASH => $public_hash[4],
			self::TOKEN_PRIVATE_HASH => $private_hash[5],
			self::TOKEN_CREATED => $token->getCreated(),
			self::TOKEN_VIDEO => $token->getVideoId(),
		]);

		return $row;
	}

	/**
	 * Gets tokens by video id.
	 *
	 * @param      int  $video_id  The video identifier
	 *
	 * @return     Database\Table\Selection  The tokens by video.
	 */
	public function getTokensByVideo(int $video_id)
	{
		return $this->database->table(self::TABLE_TOKEN)->where(self::TOKEN_VIDEO, $video_id);
	}

	/**
	 * Gets the token by identifier.
	 *
	 * @param      string  $job_id  The job_id
	 *
	 * @return     Database\Table\ActiveRow  The token with specified job_id.
	 */
	public function getTokenById(string $job_id)
	{
		return $this->database->table(self::TABLE_TOKEN)->get($job_id);
	}

	/**
	 * Update token values.
	 *
	 * @param      array  $values  New values.
	 *
	 * @return     array  Values that was different.
	 */
	public function updateToken(Database\Table\ActiveRow $row, array $values)
	{

		$stateId = $this->database->table(self::TABLE_STATE)->where(self::STATE_NAME, $values['status'])->fetchField(self::STATE_ID);

		if ($stateId === FALSE) {
			\Tracy\Debugger::log("TokenManager: Tried to update token with unknown state '".$values['status']."'", \Tracy\ILogger::ERROR);
		}
		elseif ($row->state != $stateId) { // Update status and datetime
			$success = $row->update([
				self::TOKEN_STATE => $stateId,
				self::TOKEN_LAST_UDPATED => $values['datetime']
			]);

			if ($success) return ['status' => TRUE, 'datetime' => TRUE];
		}
		else { // Just update datetime
			$success = $row->update([
				self::TOKEN_LAST_UDPATED => $values['datetime']
			]);

			if ($success) return ['datetime' => TRUE];
		}

		return [];
	}
}
