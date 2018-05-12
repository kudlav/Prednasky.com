<?php

namespace App\FrontModule\Presenters;

use Nette;
use App\Utilities;
use App\Model\TokenManager;
use App\Model\VideoManager;
use App\Model\FileManager;
use Nette\Http\Response;
use Nette\Http\Request;
use Tracy\Debugger;
use Tracy\ILogger;
use Ublaboo\Mailing\MailFactory;


class CallbackPresenter extends BasePresenter
{
	/**
	 * @var MailFactory $mailFactory
	 * @var TokenManager $tokenManager
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 */
	private $mailFactory, $tokenManager, $videoManager, $fileManager;

	public function __construct(MailFactory $mailFactory, TokenManager $tokenManager, VideoManager $videoManager, FileManager $fileManager)
	{
		$this->mailFactory = $mailFactory;
		$this->tokenManager = $tokenManager;
		$this->videoManager = $videoManager;
		$this->fileManager = $fileManager;
	}

	public function actionDefault()
	{
		$httpResponse = $this->getHttpResponse();
		$httpResponse->setContentType('text/plain', 'UTF-8');

		$httpRequest = $this->getHttpRequest();

		switch ($httpRequest->getMethod()) {
			case 'POST':
				//$this->processPost($httpRequest);
				Debugger::log('CallbackPresenter: POST request not implemented!', ILogger::ERROR);
				$this->setView('error');
				break;
			case 'GET':
				$this->processGet($httpRequest);
				break;
		}

	}

	public function processGet(Request $httpRequest)
	{
		$jobId = $httpRequest->getQuery('job_id') ?: '';
		$sign = $httpRequest->getQuery('sign') ?: '';
		$entity = [
		    'datetime' =>  date('Y-m-d H:i:s'),
		    'status' => $httpRequest->getQuery('status') ?: '',
		    'message' => $httpRequest->getQuery('message') ?: '',
		    'process' => $httpRequest->getQuery('process') ?: '',
		    'block' => $httpRequest->getQuery('block') ?: '',
		    'hostIp' => $httpRequest->getQuery('hostip') ?: '',
		    'hostName' => $httpRequest->getQuery('hostname') ?: '',
		    'sgeJobId' => intval($httpRequest->getQuery('sge_job_id'))
		];

		if ($this->parameters['sign_verification']) {
			$query = $_SERVER['QUERY_STRING'];
			$strToSign = trim(substr($query, 0, strpos($query, 'sign=')-1));
			if (!$this->verifySignature($strToSign, $sign)) {
				Debugger::log('CallbackPresenter: Recieved callback, SignatureError.', ILogger::INFO);
				$this->setView('error-signature');
				return;
			}
		}

		$recording = $this->tokenManager->getTokenById($jobId);
		if ($recording) {

			$diffArray = $this->tokenManager->updateToken($recording, $entity, $this->videoManager);

			if (isset($diffArray['status'])) {
				// invoke callback url
				//Utilities::callUrl($recording->getCallbackUrl(true));

				if ($recording['status'] != $entity['status'] && $entity['status'] == TokenManager::STATE_DONE) {

					// Add new files
					$this->fileManager->filesFromToken($recording);

					$mailClass = 'App\Model\Mail\ProcessingDoneMail';
					//send email to participants + notifications
				}
				elseif ($entity['status'] == TokenManager::STATE_ERROR) {
					$mailClass = 'App\Model\Mail\ProcessingErrorMail';
					//send email to participants and to: $this->parameters['admin_email']
				}

				if (isset($mailClass)) {
					$params = ['recording' => $recording];
				//	$mail = $this->mailFactory->createByType($mailClass, $params);
				//	$mail->send();
				}
			}

			Debugger::log('CallbackPresenter: Recieved callback, StatusAccepted.', ILogger::INFO);
			$this->setView('success');
		} else {
			Debugger::log('CallbackPresenter: Recieved callback, Error.', ILogger::INFO);
			$this->setView('error');
		}
	}
/*
	public function processPost(Request $httpRequest)
	{
		$sign = $httpRequest->getQuery('sign') ?: '';

		// potreba upravit
		if ($this->parameters['sign_verification']) {
			$strToSign = $httpRequest->getRawBody();
			if (!$this->verifySignature($strToSign, $sign)) {
				$this->setView('error-signature');
				return;
			}
		}

		if (strlen($sign) > 0) {
			$this->sgeInfo->write($httpRequest->getRawBody());
			$this->setView('success');
		} else {
			$this->setView('error');
		}
	}
*/
	/**
	 * Verify signature of request.
	 *
	 * @param string $strToSign String that is signed.
	 * @param string $signature The signature.
	 * @return bool True if signature is OK, otherwise return false.
	 */
	private function verifySignature($strToSign, $signature)
	{
		if ($signature !== sha1($strToSign . $this->parameters['salt'])) {
			return false;
		}
		return true;
	}

}
