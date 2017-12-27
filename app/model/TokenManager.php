<?php

namespace App\Model;

use Nette;


class TokenManager
{
	use Nette\SmartObject;

	const
		TABLE_TOKEN = 'token',
		TOKEN_ID = 'id',
		TOKEN_STATE = 'state',
		TOKEN_TYPE = 'type',
		TOKEN_PUBLIC_HASH = 'public_hash',
		TOKEN_PRIVATE_HASH = 'private_hash',
		TOKEN_CREATED = 'created',
		TOKEN_LAST_UDPATED = 'last_update',
		TOKEN_VIDEO = 'video'
	;

	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $database)
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
	 * @return     Nette\Database\IRow Just insertet row
	 */
	public function newToken(Token $token, int $state=1, int $type=1)
	{
		$row = $this->database->table(self::TABLE_TOKEN)->insert([
			self::TOKEN_ID => $token->getValues('job_id'),
			self::TOKEN_STATE => $state,
			self::TOKEN_TYPE => $type,
			self::TOKEN_PUBLIC_HASH => substr($token->getValues('public_datadir'), 12, -1),
			self::TOKEN_PRIVATE_HASH => substr($token->getValues('private_datadir'), 45, -1),
			self::TOKEN_CREATED => date('Y-m-d H:i:s', $token->getCreated()),
			self::TOKEN_VIDEO => $token->getVideoId(),
		]);

		return $row;
	}
}
