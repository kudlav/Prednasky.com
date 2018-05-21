<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use Nette;
use App\Model\Parameters;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
	/** @persistent */
	public $locale;

	/** @var Parameters */
	protected $parameters;

	public function injectParameters(Parameters $parameters) {
		$this->parameters = $parameters->getParam();
	}

	protected function startup()
	{
		parent::startup();

		// Periodically check CAS state
		$identity = $this->user->getIdentity();
		if ($identity !== null) {
			$now = new \DateTime();
			$timeDiff = $now->getTimestamp() - $identity->cas_check->getTimestamp();
			if ($timeDiff > $this->parameters['cas']['reauth_timeout']) {
				try {
					$this->user->setExpiration(0);
					$this->user->login($this->getHttpRequest()->getCookie($this->parameters['cas']['cookie']));
				} catch (Nette\Security\AuthenticationException $e) {
					$this->flashMessage("alert.logout_cas_timeout", 'info');
				}
			}
		}

		$this->template->user = $this->getUser();
	}
}
