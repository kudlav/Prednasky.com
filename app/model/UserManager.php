<?php
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Security;
use Nette\Security\Identity;
use Nette\Security\IIdentity;
use Tracy\Debugger;
use Tracy\ILogger;


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
	 * @param array $credentials Array with CAS id on 0 index.
	 * @return IIdentity
	 * @throws Security\AuthenticationException
	 */
	public function authenticate(array $credentials): IIdentity
	{
		if (!isset($credentials[0])) {
			throw new Security\AuthenticationException('An error occurred during authentication.', self::FAILURE);
		}
		$casId = $credentials[0];

		// Get user according to CAS id
		$user = $this->getCasUser($casId);
		if ($user === null) {
			$userInfo = $this->getLdapUser($casId);
			if ($userInfo !== null) {
				$user = $this->newUser($userInfo['cn'], $userInfo['mail'], 3, 1, $casId);
			}
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
	 * @param string $casId
	 * @return ActiveRow|null ActiveRow with user or null when there is no user with that CAS ID.
	 */
	private function getCasUser(string $casId): ?ActiveRow
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

	/**
	 * Search user by fullname or/and by email
	 *
	 * @param string $query
	 * @param string|null $secondQuery
	 * @return Selection
	 */
	public function searchUser(string $query, ?string $secondQuery = null): Selection
	{
		$selection = $this->database->table(self::TABLE_USER)
			->where(self::USER_FULLNAME .' LIKE ? OR '. self::USER_EMAIL .' LIKE ?', '%'.$query.'%', '%'.$query.'%')
		;

		if ($secondQuery !== null) {
			$selection->where(self::USER_FULLNAME .' LIKE ? OR '. self::USER_EMAIL .' LIKE ?', '%'.$secondQuery.'%', '%'.$secondQuery.'%');
		}

		return $selection;
	}

	/**
	 * Get email and fullname of VUT FIT person using LDAP search.
	 *
	 * @param string $user CAS ID.
	 * @return array|null Array containing 'cn' and 'mail' entry. Null when error.
	 */
	public function getLdapUser(string $user): ?array
	{
		$server = $this->parameters['paths']['ldap_server'];

		$connection = ldap_connect($server);
		if (($connection === false) OR (!ldap_bind($connection))) {
			Debugger::log("UserManager: Unable to connect to LDAP server '$server'", ILogger::ERROR);
			return null;
		}

		$result = ldap_list($connection, 'dc=fit,dc=vutbr,dc=cz', "uid=$user", ['mail','cn']);
		if ($result === false) {
			Debugger::log("UserManager: error when retrieving data from LDAP server '$server'", ILogger::ERROR);
			return null;
		}

		$entries = ldap_get_entries($connection, $result);
		if ($entries === false) {
			Debugger::log("UserManager: error when parsing data from LDAP server '$server'", ILogger::ERROR);
			return null;
		}

		if ($entries['count'] !== 1) {
			Debugger::log("UserManager: LDAP server '$server' contains {$entries['count']} entries for uid=$user", ILogger::ERROR);
			return null;
		}

		return [
			'cn' => $entries[0]['cn'][0],
			'mail' => $entries[0]['mail'][0],
		];
	}
}
