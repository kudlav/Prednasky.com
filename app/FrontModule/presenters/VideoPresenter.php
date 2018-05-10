<?php

namespace App\FrontModule\Presenters;

use App\Model\FileManager;
use App\Model\VideoManager;

class VideoPresenter extends BasePresenter
{

	/**
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 */
	private $videoManager, $fileManager;

	public function __construct(VideoManager $videoManager, FileManager $fileManager)
	{
		$this->videoManager = $videoManager;
		$this->fileManager = $fileManager;
	}

	public function renderDefault($id, $passphrase)
	{
		// Get file ActiveRow
		if ($id == null) {
			$this->error('Požadované video neexistuje', 404);
		}
		$this->template->video = $this->videoManager->getVideoById($id);
		if ($this->template->video === false) {
			$this->error('Požadované video neexistuje', 404);
		}
		$this->template->videoFiles = $this->fileManager->getVideoFiles($id);

		// ListGroup (left menu)
		$this->template->listGroup = [];
		$path = '';
		foreach ($this->parameters['required_tags'] as $level) {
			$item = $this->videoManager->getVideoTagValue($id, $level);
			if ($item != null) {
				$path .= $item . '/';
				$this->template->listGroup[$item] = $path;
			}
		}

		// Base path for files
		$this->template->dataExport = $this->parameters['paths']['data_export'];

		// TODO Get/download attachments
		$this->template->attachments = $this->fileManager->getVideoAttachments($id);

		$this->template->people = $this->videoManager->getVideoPeople($id);

		$this->template->relatedVideos = $this->videoManager->getRelatedVideos($id);;
	}

	protected function createComponentVideoCard()
	{
		return new VideoCard($this->fileManager, $this->parameters['paths']['data_export']);
	}

}
