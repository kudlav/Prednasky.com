<?php
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Security;
use Nette\Security\Identity;
use Nette\Security\IIdentity;


class UserManager implements Security\IAuthenticator
{
	use Nette\SmartObject;

	const
		// User table
		TABLE_USER = 'user',
		USER_CAS = 'CAS_id',
		USER_FULLNAME = 'fullname',
		USER_EMAIL = 'email',
		USER_RIGHT_GROUP = 'right_group',
		USER_INSTITUTION = 'institution',
		USER_WEB = 'personal_web',
		USER_ACTIVE = 'active'
	;

	/**
	 * @var array $parameters
	 * @var Context $database
	 */
	private $database, $parameters;

	function __construct(array $parameters, Context $database)
	{
		$this->parameters = $parameters;
		$this->database = $database;
	}

	/**
	 * Performs an authentication against e.g. database.
	 * and returns IIdentity on success or throws AuthenticationException
	 *
	 * @param array $credentials Array with token on 0 index.
	 * @return IIdentity
	 * @throws Security\AuthenticationException
	 */
	public function authenticate(array $credentials): IIdentity
	{
		if (!isset($credentials[0])) {
			throw new Security\AuthenticationException('An error occurred during authentication.', self::FAILURE);
		}
		$token = $credentials[0];

		// Get ?some? id from CAS
		$someIdFromCas = 196195; // Fake response

		// Get user according to CAS id
		$user = $this->getCasUser($someIdFromCas);
		if ($user === null) {
			$user = $this->newUser("XXX", "XXX", 3, 1, $someIdFromCas);
		}

		if ($user == null) {
			throw new Security\AuthenticationException('We are not able to authenticate you.', self::INVALID_CREDENTIAL);
		}

		$roles = array_slice($this->parameters['user_role'], 0, $user->right_group);
		$data = $user->toArray();
		$data['cas_check'] = new \DateTime();

		return new Identity($data['id'], $roles, $data); // whatever does it return instead of two null
	}

	/**
	 * Get user by CAS ID.
	 *
	 * @param int $casId
	 * @return ActiveRow|null ActiveRow with user or null when there is no user with that CAS ID.
	 */
	private function getCasUser(int $casId): ?ActiveRow
	{
		$result = $this->database->table(self::TABLE_USER)->where(self::USER_CAS, $casId)->fetch();
		return $result!==false ? $result : null;
	}

	/**
	 * Add new user.
	 *
	 * @param string $name Fullname
	 * @param string $email Email address
	 * @param int $rightGroup
	 * @param int $active 0 = inactive, 1 = active, default 1
	 * @param int $casId optional
	 * @param string $institution optional
	 * @param string $web optional
	 * @return ActiveRow|null
	 */
	private function newUser(string $name, string $email, int $rightGroup, int $active=1, int $casId=null, string $institution=null, string $web=null): ?ActiveRow
	{
		$result = $this->database->table(self::TABLE_USER)->insert([
			self::USER_CAS => $casId,
			self::USER_FULLNAME => $name,
			self::USER_EMAIL => $email,
			self::USER_RIGHT_GROUP => $rightGroup,
			self::USER_INSTITUTION => $institution,
			self::USER_WEB => $web,
			self::USER_ACTIVE => $active
		]);
		return $result!==false ? $result : null;
	}

	/**
	 * Check whether the CAS validation is required.
	 *
	 * @param IIdentity|null $identity
	 * @return bool
	 */
	public function casExpireCheck(?IIdentity $identity): bool
	{
		if ($identity !== null) {
			$now = new \DateTime();
			$timeDiff = $now->getTimestamp() - $identity->cas_check->getTimestamp();
			if ($timeDiff > $this->parameters['cas']['reauth_timeout']) {
				return true;
			}
		}
		return false;
	}
}
