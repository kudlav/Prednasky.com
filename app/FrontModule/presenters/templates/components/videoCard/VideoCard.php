<?php

namespace App\FrontModule\Presenters;

use Nette;
use App\Model\FileManager;
use Nette\Application\UI\Control;
use Nette\Database\Table\ActiveRow;


class VideoCard extends Control
{
	/**
	 * @var FileManager $fileManager
	 */
	private $fileManager, $filepath;

	public function __construct(FileManager $fileManager, string $filepath)
	{
		$this->fileManager = $fileManager;
		$this->filepath = $filepath;
	}

	public function render(ActiveRow $video)
	{
		$this->template->setFile(__DIR__.'/videoCard.latte');

		$this->template->video = $video;

		$thumbnail = $this->fileManager->getVideoThumbnail($video->id);
		if($thumbnail) {
			$this->template->thumbnail = $this->filepath . '/' . $thumbnail->ref(FileManager::TABLE_FILE)->path;
		}
		else {
			$this->template->thumbnail = null;
		}

		$this->template->render();
	}
}
