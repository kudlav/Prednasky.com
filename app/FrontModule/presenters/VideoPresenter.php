<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use Nette;
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

	public function renderDefault(int $id, string $passphrase=""): void
	{
		// Get file ActiveRow
		if ($id == null) {
			$this->error('Požadované video neexistuje', Nette\Http\IResponse::S404_NOT_FOUND);
		}
		$this->template->video = $this->videoManager->getVideoById($id);
		if ($this->template->video === false) {
			$this->error('Požadované video neexistuje', Nette\Http\IResponse::S404_NOT_FOUND);
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
		$this->template->dataExport = $this->parameters['paths']['url_data_export'];

		// TODO Get/download attachments
		$this->template->attachments = $this->fileManager->getVideoAttachments($id);

		$this->template->people = $this->videoManager->getVideoPeople($id);

		$this->template->relatedVideos = $this->videoManager->getRelatedVideos($id);;
	}

	protected function createComponentVideoCard(): VideoCard
	{
		return new VideoCard($this->fileManager, $this->parameters['paths']['url_data_export']);
	}

}
