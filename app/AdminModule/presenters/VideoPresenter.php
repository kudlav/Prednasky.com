<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use App\Model\FileManager;
use App\Model\VideoManager;
use App\AdminModule\Forms\EditVideoFormFactory;
use Kdyby\Translation\Translator;


class VideoPresenter extends BasePresenter
{
	/**
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 */
	private $videoManager, $fileManager, $translator;

	public function __construct(VideoManager $videoManager, FileManager $fileManager, Translator $translator)
	{
		$this->videoManager = $videoManager;
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
		$factory = new EditVideoFormFactory($this->videoManager, $this->presenter, $this->translator);
		return $factory->create($this->template->video);
	}
}
