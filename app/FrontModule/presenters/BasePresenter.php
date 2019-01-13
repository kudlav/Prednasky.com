<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use Nette;
use App\Model\UserManager;
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

	/** @var UserManager */
	private $userManager;

	public function injectParameters(Parameters $parameters) {
		$this->parameters = $parameters->getParam();
	}

	public function injectUserManager(UserManager $userManager)
	{
		$this->userManager = $userManager;
	}

	protected function startup()
	{
		parent::startup();

		$this->template->user = $this->getUser();
	}
}
