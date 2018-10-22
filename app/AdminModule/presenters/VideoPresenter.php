<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use Kdyby\Translation\Translator;
use App\Model\FileManager;
use App\Model\VideoManager;
use App\Model\UserManager;
use App\Model\TokenManager;
use App\AdminModule\Forms\EditVideoFormFactory;
use Nette\Http\IResponse;
use Tracy\Debugger;


class VideoPresenter extends BasePresenter
{
	/**
	 * @var VideoManager $videoManager
	 * @var UserManager $userManager
	 * @var FileManager $fileManager
	 * @var TokenManager $tokenManager
	 * @var Translator $translator
	 */
	private $videoManager, $userManager, $fileManager, $tokenManager, $translator;

	public function __construct(VideoManager $videoManager, UserManager $userManager, FileManager $fileManager, TokenManager $tokenManager, Translator $translator)
	{
		$this->videoManager = $videoManager;
		$this->userManager = $userManager;
		$this->fileManager = $fileManager;
		$this->tokenManager = $tokenManager;
		$this->translator = $translator;
	}

	public function renderUpload(): void
	{
		$this->template->resDropzone = true;
	}

	public function handleUploadPart(): void
	{
		if (!$this->fileManager->uploadFilePart($this->user->id, $this->getHttpRequest())) {
			$this->getHttpResponse()->setCode(IResponse::S400_BAD_REQUEST);
			$this->sendJson('Chyba nahrávání souboru!');
		}
		$this->sendJson('success');
	}

	public function handleUploadEnd()
	{
		// Compile upload
		$httpRequest = $this->getHttpRequest();
		$id = $httpRequest->getQuery('id');
		$filename = $httpRequest->getQuery('filename');

		if ($id === null || $filename === null || strpos($id, '/')!==false || strpos($filename, '/')!==false) {
			$this->getHttpResponse()->setCode(IResponse::S400_BAD_REQUEST);
			$this->sendJson('Chybí parametry pro zkompilování uploadu!');
		}

		$this->fileManager->uploadFileEnd((string) $id, 'video.out');

		Debugger::log('VideoPresenter: User '. $this->user->id .' uploaded file "'. $filename .'"', Debugger::INFO);

		// Create video and token
		$videoID = $this->videoManager->newVideo($this->user, (string) $filename);

		$allValues = $this->tokenManager->getTokenDefaults();
		$allValues['input_media'] = $this->fileManager->getTempDir() .'/'. $id . '/video.out';

		if ($this->tokenManager->submitToken($this->tokenManager->getTemplateByName('config_video_convert.ini'), $allValues, $videoID) === null) {
			$this->videoManager->removeVideo($videoID);
			$this->getHttpResponse()->setCode(IResponse::S500_INTERNAL_SERVER_ERROR);
			$this->sendJson($this->translator->translate('alert.run_task_failed'));
		}

		$this->sendJson($this->link('Video:edit', ['id' => $videoID]));
	}

	public function renderEdit(int $id): void
	{
		// Check if the video exists
		$this->template->video = $this->videoManager->getVideoById($id, true);
		if ($this->template->video === null) {
			$this->error('Video s id '. $id .' neexistuje!', Nette\Http\IResponse::S404_NOT_FOUND);
		}

		// Check the user rights for this video
		if (!$this->user->isInRole('admin')) {
			if (!isset($this->videoManager->getVideosByUser($this->user)[$id])) {
				$this->error('Nemáte oprávnění k editování totoho videa', Nette\Http\IResponse::S403_FORBIDDEN);
			}
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

		$this->template->resDatepicker = true;
		$this->template->resClockpicker = true;
		$this->template->resSelect = true;
		$this->template->resTinymce = true;
	}

	/**
	 * @secured
	 * @param int $id Video ID.
	 * @throws Nette\Application\AbortException
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
	 * @param int $id Video ID.
	 * @throws Nette\Application\AbortException
	 */
	public function handleDelLink(int $id)
	{
		$this->payload->message = $this->videoManager->setShareLink($id, true);
		$this->payload->status = 'ok';

		$this->sendPayload();
	}

	public function handleDel(int $id)
	{
		$videoName = $this->videoManager->getVideoById($id, true)->name;
		if ($this->videoManager->removeVideo($id)) {
			$msg = $this->translator->translate('alert.video_delete_successfully', ['name' => $videoName]);
			$this->flashMessage($msg, 'success');
			$this->redirect('Videos:published');
		}
		else {
			$msg = $this->translator->translate('alert.video_delete_failed', ['name' => $videoName]);
			$this->flashMessage($msg, 'danger');
		}
	}

	protected function createComponentEditVideoForm()
	{
		$video = $this->videoManager->getVideoById((int) $this->getParameter('id'), true);
		$factory = new EditVideoFormFactory($this->videoManager, $this->userManager, $this->presenter, $this->translator, $video, $this->parameters['structure_tag']);
		return $factory->create();
	}
}
