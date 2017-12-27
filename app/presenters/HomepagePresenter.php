<?php

namespace App\Presenters;

use App\Model\Token;
use App\Model\VideoManager;
use \App\Model\TokenManager;

class HomepagePresenter extends BasePresenter
{

	/**
	 * @var VideoManager $videoManager
	 * @var TokenManager $tokenManager
	 */
	private $videoManager, $tokenManager;

	public function __construct(VideoManager $videoManager, TokenManager $tokenManager)
	{
		$this->videoManager = $videoManager;
		$this->tokenManager = $tokenManager;
	}

	public function renderDefault($video_url)
	{
		if (isset($video_url)) {
			$videoId = $this->videoManager->newVideo();

			$token = new Token($videoId, $this->tokenManager);
			$token->setTemplate('config_youtube_downloader');
			$token->setValues([
				'opt_input_url' => $video_url
			]);
			if (!$token->submit()) {
				\Tracy\Debugger::log('HomepagePresenter: Unable to create token.', \Tracy\ILogger::ERROR);
				$this->error();
			}
			\Tracy\Debugger::log("HomepagePresenter: Created token with 'job_id':'".$token->getValues('job_id')."'", \Tracy\ILogger::INFO);
			echo($token->getValues('callback_base_url'). 'spokendata-submitter' .$token->getValues('public_datadir'));
		}
		else {
			$this->template->videos = $this->videoManager->getAllVideos();
		}
	}
}
