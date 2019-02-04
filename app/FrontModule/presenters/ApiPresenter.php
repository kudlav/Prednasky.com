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
			$thumbnail = $this->fileManager->getVideoThumbnail((int)$video->id);
			if (isset($thumbnail)) {
				$thumbnail = $this->parameters['paths']['url_data_export'] . '/' . $thumbnail->path;
			}
			$record_begin = (isset($video->record_date)) ? $video->record_date->format('j. n. Y') : null;
			if (isset($record_begin, $video->record_time_begin)) {
				$record_begin .= ' ' . $video->record_time_begin->h . sprintf(':%02d', $video->record_time_begin->i);
			}
			$result[] = [
				'id' => $video->id,
				'name' => $video->name,
				'thumbnail' => $thumbnail,
				'abstract' => $video->abstract,
				'record_begin' => $record_begin,
				'duration' => (isset($video->duration) ? gmdate("H:i:s",$video->duration) : null) ,
				'url' => $this->link('Video:', ['id' => $video->id]),
			];
		}

		$this->sendResponse(new JsonResponse(['videos' => array_values($result)]));
	}
}
