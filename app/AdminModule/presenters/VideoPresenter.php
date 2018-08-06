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

	}

	public function handleUploadPart(): void
	{
		$httpRequest = $this->getHttpRequest();
		$file = $httpRequest->getFile('file');
		if ($file===null || strpos($httpRequest->getPost('dzuuid'), '/')!==false || !$this->fileManager->uploadFilePart($file, $httpRequest->getPost('dzuuid'), (int) $httpRequest->getPost('dzchunkindex'))) {
			Debugger::log('VideoPresenter: User '. $this->user->id .' tried to upload file "'. $file->getName() .'", error code '. $file->getError(), Debugger::INFO);
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

		$this->fileManager->uploadFileEnd((string) $id, (string) $filename);

		Debugger::log('VideoPresenter: User '. $this->user->id .' uploaded file "'. $filename .'"', Debugger::INFO);

		// Create video and token
		$videoID = $this->videoManager->newVideo((string) $filename);

		$allValues = $this->tokenManager->getTokenDefaults();
		$allValues['input_media'] = $this->fileManager->getTempDir() .'/'. $id .'/video.mp4';

		if ($this->tokenManager->submitToken($this->tokenManager->getTemplateByName('config_video_convert.ini'), $allValues, $videoID) === null) {
			try {
				$this->videoManager->removeVideo($videoID);
			} catch (\Exception $e) {}
			$this->getHttpResponse()->setCode(IResponse::S500_INTERNAL_SERVER_ERROR);
			$this->sendJson($this->translator->translate('alert.run_task_failed'));
		}

		$this->sendJson($videoID);
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
			$tags = [];
			$empty = true;
			foreach ($this->parameters['structure_tag'] as $tag) {
				$tagRow = $this->videoManager->getVideoTagValue($id, $tag);
				if ($tagRow !== null) {
					$tags[$tag] = $tagRow->id;
					$empty = false;
				}
				else {
					$tags[$tag] = null;
				}
			}
			if (!$empty) {
				$courses = $this->userManager->getUserCourses($this->presenter->user->id);
				$courseMatch = $this->userManager->isUserCourse($courses, $this->parameters['structure_tag'], $tags);
				if (!$courseMatch) {
					$this->error('Nemáte oprávnění k editování totoho videa', Nette\Http\IResponse::S403_FORBIDDEN);
				}
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
	}

	/**
	 * @secured
	 * @param int $id Video ID
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
