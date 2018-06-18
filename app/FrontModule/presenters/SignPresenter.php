<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Model\UserManager;
use Nette\Security\AuthenticationException;


class SignPresenter extends BasePresenter
{
	/**
	 * @var UserManager $userManager
	 */
	private $userManager;

	public function __construct(UserManager $userManager)
	{
		$this->userManager = $userManager;
	}

	public function actionIn(int $cas=0): void
	{
		try {
			$this->user->setExpiration(0);
			$this->user->login('1234');
			// TODO $this->user->login($this->getHttpRequest()->getCookie($this->parameters['cas']['cookie']));
		}
		catch (AuthenticationException $e) {
			if ($cas===1) {
				$this->flashMessage("alert.login_cas_err", 'danger');
				$this->redirect('Homepage:default');
			}
			$thisUrl = $this->getHttpRequest()->getUrl()->absoluteUrl;
			$this->redirectUrl($this->parameters['cas']['url'] .'?'. $this->parameters['cas']['cookie'] .'&'. $thisUrl .'?cas=1');
		}
		$this->flashMessage("alert.login_cas_ok", 'success');
		$this->redirect('Homepage:default');
	}

	public function actionOut(): void
	{
		$this->user->logout(true);
		$referer = $this->getHttpRequest()->getReferer();

		if ($referer !== null) {
			$this->redirectUrl($referer->absoluteUrl);
		}
		else {
			$this->flashMessage("alert.logout_ok", 'success');
			$this->redirect('Homepage:default');
		}
	}
}
