<?php

namespace App\Presenters;

use Nette;
use App\Model\Repository\ProcessingRepository;
use App\Model\Repository\RecordingRepository;
use App\Model\Entity\Processing;
use App\Model\Entity\Recording;
use DateTime;
use Tracy\Debugger;
use Ublaboo\Mailing\MailFactory;
use App\Utilities;
use Nette\Http\Request;
use App\Model\SgeInfo;


class CallbackPresenter extends Nette\Application\UI\Presenter
{
	/** @var ProcessingRepository @inject */
	public $processingRepository;

	/** @var RecordingRepository @inject */
	public $recordingRepository;

	/** @var MailFactory @inject */
	public $mailFactory;

	/** @var SgeInfo @inject */
	public $sgeInfo;


	public function actionDefault()
	{
		$httpRequest = $this->getHttpRequest();

		switch ($httpRequest->getMethod()) {
			case 'POST':
				$this->processPost($httpRequest);
				break;
			case 'GET':
				$this->processGet($httpRequest);
				break;
		}

	}

	public function processGet(Request $httpRequest)
	{
		$jobId = $httpRequest->getQuery('job_id') ?: '';
		$status = $httpRequest->getQuery('status') ?: '';
		$message = $httpRequest->getQuery('message') ?: '';
		$process = $httpRequest->getQuery('process') ?: '';
		$block = $httpRequest->getQuery('block') ?: '';
		$hostIp = $httpRequest->getQuery('hostip') ?: '';
		$hostName = $httpRequest->getQuery('hostname') ?: '';
		$sgeJobId = intval($httpRequest->getQuery('sge_job_id'));
		$sign = $httpRequest->getQuery('sign') ?: '';


		if ($this->context->parameters['signVerification']) {
			$query = $_SERVER['QUERY_STRING'];
			$strToSign = trim(substr($query, 0, strpos($query, 'sign=')-1));
			if (!$this->verifySignature($strToSign, $sign)) {
				$this->setView('error-signature');
				return;
			}
		}

		$recording = $this->recordingRepository->getByJobId($jobId);
		if ($recording) {
			$entity = new Processing([
			    'recording' => $recording,
			    'datetime' => new DateTime,
			    'block' => $block,
			    'status' => $status,
			    'process' => $process,
			    'message' => $message,
			    'hostIp' => $hostIp,
			    'hostName' => $hostName,
			    'sgeJobId' => $sgeJobId
			]);

			$this->processingRepository->insert($entity);
			$diffArray = $this->recordingRepository->updateByProcessing($recording, FALSE);

			if (isset($diffArray['status']) && in_array($recording->getStatus(), [Recording::STATUS_DONE, Recording::STATUS_ERROR])) {

				// invoke callback url
				Utilities::callUrl($recording->getCallbackUrl(TRUE));

				if ($recording->getStatus() == Recording::STATUS_DONE) {
					$mailClass = 'App\Model\Mail\ProcessingDoneMail';
				} elseif ($recording->getStatus() == Recording::STATUS_ERROR) {
					$mailClass = 'App\Model\Mail\ProcessingErrorMail';
				}

				if (isset($mailClass)) {
					$params = ['recording' => $recording];
					$mail = $this->mailFactory->createByType($mailClass, $params);
					$mail->send();
				}
			}

			$this->setView('success');
		} else {
			$this->setView('error');
		}
	}

	public function processPost(Request $httpRequest)
	{
		$sign = $httpRequest->getQuery('sign') ?: '';

		// potreba upravit
		if ($this->context->parameters['signVerification']) {
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

	private function verifySignature($strToSign, $signature)
	{
		if ($signature !== sha1($strToSign . $this->context->parameters['salt'])) {
			Debugger::log('Signature error ' . $signature . ' :: ' .$strToSign);
			return FALSE;
		}
		return TRUE;
	}

}
