<?php

namespace App\FrontModule\Presenters;

use App\Model\Token;
use App\Model\TokenManager;
use App\Model\VideoManager;

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

	public function renderDefault($path)
	{
		$tags = explode('/', $path);
		$path = $path ? $path.'/' : '';

		$actualTag = 0;
		$tagValues = [];

		foreach ($tags as $tagValue) {
			if ($tagValue != '' && isset($this->parameters['required_tags'][$actualTag])) {
				\Tracy\Debugger::barDump($actualTag);
				if ($this->videoManager->issetTagValue($this->parameters['required_tags'][$actualTag], $tagValue)) {
					$actualTag++;
				}
				else {
					$this->error();
				}
			}
		}
		$tagValues = $this->videoManager->getTagValues($this->parameters['required_tags'][$actualTag]);

		$this->template->listGroupTitle = $this->parameters['required_tags'][$actualTag];
		$this->template->listGroup = [];
		foreach ($tagValues as $value) {
			$this->template->listGroup[$value] = $path.$value;
		}

		if ($actualTag > 0) {
			$this->template->listGroup['<i class="fa fa-share fa-lg">&nbsp;</i>Back to '.$this->parameters['required_tags'][$actualTag-1]] = implode('/', array_slice($tags, 0, -1));
		}
	}

	public function renderDownload($video_url)
	{
		if (isset($video_url)) {
			$videoId = $this->videoManager->newVideo();

			$token = new Token($videoId, $this->tokenManager, $this->parameters);
			if (!$token->setTemplate('config_youtube_downloader')) {
				\Tracy\Debugger::log("HomepagePresenter: Unable to create token. Template 'config_youtube_downloader' doesn't exist", \Tracy\ILogger::ERROR);
				$this->error("HomepagePresenter: Unable to create token. Template 'config_youtube_downloader' doesn't exist", 500);
			}
			$token->setValues([
				'opt_input_url' => $video_url
			]);
			if (!$token->submit()) {
				\Tracy\Debugger::log('HomepagePresenter: Unable to create token.', \Tracy\ILogger::ERROR);
				$this->error("HomepagePresenter: Unable to create token.", 500);
			}
			\Tracy\Debugger::log("HomepagePresenter: Created token with 'job_id':'".$token->getValues('job_id')."'", \Tracy\ILogger::INFO);
			echo($token->getValues('callback_base_url'). 'spokendata-submitter' .$token->getValues('public_datadir'));
		}
		else {
			$this->template->videos = $this->videoManager->getAllVideos();
			$this->template->tokens = [];

			foreach ($this->template->videos as $row) {
				$this->template->tokens[$row->id] = $this->tokenManager->getTokensByVideo($row->id);
			}
		}
	}
}
