<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use Nette;
use App\Model\FileManager;
use App\Model\Token;
use App\Model\TokenManager;
use App\Model\VideoManager;


class HomepagePresenter extends BasePresenter
{
	/**
	 * @var VideoManager $videoManager
	 * @var TokenManager $tokenManager
	 * @var FileManager $fileManager
	 */
	private $videoManager, $tokenManager, $fileManager;

	public function __construct(VideoManager $videoManager, TokenManager $tokenManager, FileManager $fileManager)
	{
		$this->videoManager = $videoManager;
		$this->tokenManager = $tokenManager;
		$this->fileManager = $fileManager;
	}

	public function renderDefault(string $path="", int $page=1): void
	{
		$tags = array_filter(explode('/', $path));
		$path = $path ? $path.'/' : '';

		$tagValues = $this->videoManager->getNestedTagValues($tags);
		if ($tagValues === null) { // Tags were invalid
			$this->error();
		}

		// Breadcrumb
		$link = '';
		$this->template->breadcrumb = [];
		foreach ($tags as $item) {
			$link .= $item. '/';
			$this->template->breadcrumb[$item] = $link;
		}

		// ListGroup (left menu)
		if (!empty($tagValues['val'])) { // Show title when not empty list
			$this->template->listGroupTitle = $this->parameters['structure_tag'][$tagValues['lvl']];
		}
		$this->template->listGroup = [];
		foreach ($tagValues['val'] as $value) {
			$this->template->listGroup[$value] = $path.$value;
		}

		if (!empty($tags)) {
			// Root tag, no link to upper tag
			$this->template->listGroupBackText = $this->parameters['structure_tag'][count($tags)-1];
			$this->template->listGroupBackLink = implode('/', array_slice($tags, 0, -1));

			// List of videos
			$this->template->title = end($tags);
			$lastPage = 0;
			$this->template->videoList = $this->videoManager->getVideos(0, $this->user->loggedIn, false, $tagValues['lvl'], $tagValues['vid'])
				->page($page, 12, $lastPage);
		}
		else {
			$lastPage = 1;
			$this->template->videoList = $this->videoManager->getVideos(12, $this->user->loggedIn);
		}

		// Paginator
		$this->template->page = $page;
		$this->template->lastPage = $lastPage;

		$this->template->resSelect = true;
	}

	protected function createComponentVideoCard(): VideoCard
	{
		return new VideoCard($this->fileManager, $this->parameters['paths']['url_data_export']);
	}
}
