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
		$tags = array_filter(explode('/', $path));
		$path = $path ? $path.'/' : '';

		$tagValues = $this->videoManager->getNestedTagValues($tags);
		if ($tagValues === NULL) {
			$this->error();
		}

		$link = '';
		$this->template->breadcrumb = [];
		foreach ($tags as $item) {
			$link .= $item. '/';
			$this->template->breadcrumb[$item] = $link;
		}

		if (!empty($tagValues['val'])) { // Show title when not empty list
			$this->template->listGroupTitle = $tagValues['lvl'];
		}
		$this->template->listGroup = [];
		foreach ($tagValues['val'] as $value) {
			$this->template->listGroup[$value] = $path.$value;
		}

		// Root tag, no link to upper tag
		if (!empty($tags)) {
			$this->template->title = end($tags);
			$this->template->listGroupBackText = $this->parameters['required_tags'][count($tags)-1];
			$this->template->listGroupBackLink = implode('/', array_slice($tags, 0, -1));
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
