<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use Kdyby\Translation\Translator;
use App\Model\FileManager;
use App\Model\VideoManager;
use App\Model\UserManager;
use App\AdminModule\Forms\EditVideoFormFactory;


class VideoPresenter extends BasePresenter
{
	/**
	 * @var VideoManager $videoManager
	 * @var UserManager $userManager
	 * @var FileManager $fileManager
	 * @var Translator $translator
	 */
	private $videoManager, $userManager, $fileManager, $translator;

	public function __construct(VideoManager $videoManager, UserManager $userManager, FileManager $fileManager, Translator $translator)
	{
		$this->videoManager = $videoManager;
		$this->userManager = $userManager;
		$this->fileManager = $fileManager;
		$this->translator = $translator;
	}

	public function renderEdit(int $id): void
	{
		$this->template->video = $this->videoManager->getVideoById($id, true);
		if ($this->template->video === null) {
			$this->error('Video s id '. $id .' neexistuje!', Nette\Http\IResponse::S404_NOT_FOUND);
		}

		$this->template->shareLink = $this->videoManager->getShareLink($id);
		if ($this->template->shareLink !== null) {
			$this->template->shareLink = $this->template->baseUrl . $this->link(':Front:Video:default', [$id, 'p' => $this->template->shareLink]);
		}

		$this->template->thumbnail = $this->fileManager->getVideoThumbnail($id);
		if ($this->template->thumbnail !== null) {
			$this->template->thumbnail = $this->parameters['paths']['url_data_export'] .'/'. $this->template->thumbnail->path;
		}

		$this->template->prevPage = $this->getHttpRequest()->getReferer() ?? $this->link('Videos:');
		$this->template->structureTags =  $this->parameters['structure_tag'];
	}

	/**
	 * @secured
	 */
	public function handleAddLink(int $id)
	{
		try {
			$token = $this->videoManager->setShareLink($id);
			$this->payload->message = $this->template->baseUrl . $this->link(':Front:Video:default', [$id, 'p' => $token]);
			$this->payload->status = 'ok';
		}
		catch (\Exception $e) {
			$this->payload->status = 'err';
		}
		$this->sendPayload();
	}

	/**
	 * @secured
	 */
	public function handleDelLink(int $id)
	{
		$this->payload->message = $this->videoManager->setShareLink($id, true);
		$this->payload->status = 'ok';

		$this->sendPayload();
	}

	protected function createComponentEditVideoForm()
	{
		$video = $this->videoManager->getVideoById((int) $this->getParameter('id'), true);
		$factory = new EditVideoFormFactory($this->videoManager, $this->userManager, $this->presenter, $this->translator, $video, $this->parameters['structure_tag']);
		return $factory->create();
	}
}
