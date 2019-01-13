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

		$this->setLayout(NULL);

		ob_start();
		require_once("../cosign/cosign.php");

		if (!cosign_auth(array(
			//
			// setup validation arguments here, don't put them into cosign_config.php!
			//
			"CosignValidLocation"=>"nothing",
			"CosignValidationErrorRedirect"=>"https://cas.fit.vutbr.cz/validation_error.html",
			"CosignValidReference"=>"#^https?:\/\/prednasky\.com(\/.*)?#",
		))) {
			error_log("cosign valid service failed");
			ob_end_flush();
			header("503 Service Temporarily Unavailable");
			echo "Cosign validation service failed";
			exit();
		}
	}

	public function actionCosignValid()
	{
		require_once("../cosign/cosign.php");

		if (!cosign_auth(array(
			// setup validation arguments here, don't put them into cosign_config.php!
			"CosignValidLocation"=>"/index.php",
			"CosignValidationErrorRedirect"=>"https://cas.fit.vutbr.cz/validation_error.html",
			"CosignValidReference"=>"#^https?:\/\/prednasky\.com(\/.*)?#",
		))) {
			error_log("cosign valid service failed");
			$this->error("Cosign validation service failed", 503);
		}

		// Verified FIT person
		$this->user->setExpiration(0);
		try {
			$this->user->login($_SERVER['REMOTE_USER']);
		}
		catch (AuthenticationException $e) {
			$this->flashMessage("alert.login_cas_err", 'danger');
			$this->redirect('Homepage:default');
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
