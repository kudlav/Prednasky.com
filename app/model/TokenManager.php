<?php

namespace App\Model;

use Nette;
use Nette\Database;
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
		TOKEN_LAST_UDPATED = 'last_update',
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

		// State values
		STATE_SUBMITTED = 'submitted',
		STATE_START = 'start',
		STATE_INFO = "info",
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
	 * @return     Database\IRow|false  Just insertet row
	 */
	public function newToken(Token $token, int $state=1, int $type=1)
	{
		$public_hash = explode('/', $token->getValues('public_datadir'));
		$private_hash = explode('/', $token->getValues('private_datadir'));

		$template = $this->getTemplateByName($token->getTemplate());
		if ($template === FALSE) {
			Debugger::log("TokenManager.php: Template '".$token->getTemplate()."' not found in database.", \Tracy\ILogger::ERROR);
			return FALSE;
		}

		$row = $this->database->table(self::TABLE_TOKEN)->insert([
			self::TOKEN_ID => $token->getValues('job_id'),
			self::TOKEN_STATE => $state,
			self::TOKEN_TYPE => $type,
			self::TOKEN_TEMPLATE => $template->id,
			self::TOKEN_PENDING => $template->blocks,
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
	 * Update token values
	 *
	 * @param   Database\Table\ActiveRow  $row  Token database row
	 * @param   array  $values  New values
	 * @param   VideoManager  $videoManager
	 *
	 * @return  bool  Success or error.
	 */
	public function updateToken(Database\Table\ActiveRow $row, array $values, VideoManager $videoManager)
	{
		$stateId = $this->database->table(self::TABLE_STATE)
			->where(self::STATE_NAME, $values['status'])
			->fetchField(self::STATE_ID)
		;

		if ($stateId === FALSE) {
			\Tracy\Debugger::log("TokenManager: Tried to update token with unknown state '".$values['status']."'", \Tracy\ILogger::ERROR);
		}
		else {
			// Always update time of last update
			$data = [
				self::TOKEN_LAST_UDPATED => $values['datetime']
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
					$videoManager->setDuration($row->video, $value[1]);
				}
			}

			$success = $row->update($data);

			if ($success) return true;
		}

		return false;
	}


	/**
	 * Gets the template by name.
	 *
	 * @param string $name Filename of template
	 *
	 * @return Nette\Database\Table\ActiveRow|false if there is no such template
	 */
	public function getTemplateByName(string $name="")
	{
		return $this->database->table(self::TABLE_TEMPLATE)
			->where(self::TEMPLATE_NAME, $name)
			->fetch();
	}
}
