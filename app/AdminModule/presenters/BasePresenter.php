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

		if (!$this->user->isInRole('teacher')) {
			$this->error('Nemáte dostatečná oprávnění k zobrazení této stránky.', Nette\Http\IResponse::S403_FORBIDDEN);
		}

		// Flush flash messages in session
		$session = $this->getSession('flashMessages');
		if (isset($session->flashMessages)) {
			foreach ($session->flashMessages as $flashMessage) {
				$this->flashMessage($flashMessage['msg'], $flashMessage['type']);
			}
			unset($session->flashMessages);
		}

		$this->template->user = $this->getUser();
		$this->template->locale = $this->locale;
	}

	protected function createComponentDashboardMenu(): DashboardMenu
	{
		return new DashboardMenu();
	}
}
