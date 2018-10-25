<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use Nette;
use Nette\Application\Responses\JsonResponse;
use App\Model\VideoManager;
use App\Model\FileManager;


class ApiPresenter extends BasePresenter
{
	/**
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 */
	private $videoManager, $fileManager;

	/**
	 * ApiPresenter constructor.
	 * @param VideoManager $videoManager
	 * @param FileManager $fileManager
	 */
	public function __construct(VideoManager $videoManager, FileManager $fileManager)
	{
		$this->videoManager = $videoManager;
		$this->fileManager = $fileManager;
	}

	/**
	 * @param string $query
	 * @throws Nette\Application\AbortException
	 * @throws Nette\Application\UI\InvalidLinkException
	 */
	public function renderSearchVideo(string $query)
	{
		$result = [];
		$videoRows = $this->videoManager->searchVideos($query, $this->user->loggedIn);
		foreach ($videoRows as $video) {
			$thumbnail = $this->fileManager->getVideoThumbnail((int) $video->id);
			if ($thumbnail !== null) $thumbnail = $this->parameters['paths']['url_data_export'] . '/' . $thumbnail;
			$result[] = [
				'name' => $video->name,
				'thumbnail' => $thumbnail,
				'abstract' => $video->abstract,
				'record_begin' => ($video->record_begin !== null ? $video->record_begin->format('j. n. Y H:i') : null),
				'duration' => ($video->duration !== null ? gmdate("H:i:s",$video->duration) : null) ,
				'url' => $this->link('Video:', ['id' => $video->id]),
			];
		}

		$this->sendResponse(new JsonResponse(['videos' => array_values($result)]));
	}
}
