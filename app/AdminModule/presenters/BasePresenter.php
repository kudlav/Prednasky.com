<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use App\Model\UserManager;
use App\Model\Parameters;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
	use \Nextras\Application\UI\SecuredLinksPresenterTrait;

	/** @persistent */
	public $locale;

	/** @var Parameters */
	protected $parameters;

    /** @var UserManager */
	private $userManager;

	public function injectParameters(Parameters $parameters): void
	{
		$this->parameters = $parameters->getParam();
	}

	public function injectUserManager(UserManager $userManager): void
	{
		$this->userManager = $userManager;
	}

	protected function startup(): void
	{
		parent::startup();

		// Periodically check CAS state
		if ($this->userManager->casExpireCheck($this->user->getIdentity())) {
			try {
				$this->user->setExpiration(0);
				$this->user->login('1234'); // TODO $this->user->login($this->getHttpRequest()->getCookie($this->parameters['cas']['cookie']));
			} catch (Nette\Security\AuthenticationException $e) {
				$this->flashMessage("alert.logout_cas_timeout", 'info');
			}
		}

		if (!$this->user->isInRole('teacher')) {
			$this->error('Nemáte dostatečná oprávnění k zobrazení této stránky.', Nette\Http\IResponse::S403_FORBIDDEN);
		}

		$this->template->user = $this->getUser();
		$this->template->locale = $this->locale;
	}

	protected function createComponentDashboardMenu(): DashboardMenu
	{
		return new DashboardMenu();
	}
}
