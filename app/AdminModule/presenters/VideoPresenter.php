<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\AdminModule\Forms\YoutubeUploadFormFactory;
use Nette;
use Kdyby\Translation\Translator;
use App\Model\FileManager;
use App\Model\VideoManager;
use App\Model\UserManager;
use App\Model\TokenManager;
use App\AdminModule\Forms\EditVideoFormFactory;
use Nette\Application\UI\Form;
use Nette\Http\IResponse;
use Tracy\Debugger;
use Tracy\ILogger;


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
		parent::__construct();

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

	public function handleUploadEnd(): void
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

		$session = $this->getSession('flashMessages');
		if (!isset($session->flashMessages)) {
			$session->flashMessages = [];
		}

		if ($this->tokenManager->submitToken($this->tokenManager->getTemplateByName('config_video_convert.ini'), $allValues, $videoID) === null) {
			$session->flashMessages[] = [
				'msg' => 'alert.video_upload_failed',
				'type' => 'danger',
			];
			$this->videoManager->removeVideo($videoID);
			$this->getHttpResponse()->setCode(IResponse::S500_INTERNAL_SERVER_ERROR);
			$this->sendJson($this->translator->translate('alert.video_upload_failed'));
		}

		$session->flashMessages[] = [
			'msg' => 'alert.video_upload_successfully',
			'type' => 'success',
		];

		$this->sendJson($this->link('Video:edit', ['id' => $videoID]));
	}

	/**
	 * @throws Nette\Application\AbortException
	 */
	public function handleAddPeople(): void
	{
		$info = $this->managePeople();

		// Add new relation between user and video
		if (!$this->videoManager->addVideoPeople($info['userId'], $info['videoId'], $info['roleId'], $info['showEmail'])) {
			$this->payload->status = $this->translator->translate('alert.role_exists');
			$this->getHttpResponse()->setCode(IResponse::S400_BAD_REQUEST);
			$this->sendPayload();
		}

		$this->payload->status = 'ok';
		$this->sendPayload();
	}

	/**
	 * @throws Nette\Application\AbortException
	 */
	public function handleRemovePeople(): void
	{
		$info = $this->managePeople();

		// Remove relation between user and video
		if (!$this->videoManager->removeVideoPeople($info['userId'], $info['videoId'], $info['roleId'])) {
			$this->payload->status = $this->translator->translate('alert.role_remove_error');
			$this->getHttpResponse()->setCode(IResponse::S400_BAD_REQUEST);
			$this->sendPayload();
		}

		$this->payload->status = 'ok';
		$this->sendPayload();
	}

	/**
	 * @return array Contains: showEmail, videoId, roleId, userId
	 * @throws Nette\Application\AbortException
	 */
	public function managePeople(): array
	{
		$user = (string) $this->getParameter('name');
		$info = [
			'showEmail' => (bool) ($this->getParameter('show_email') == "true"),
			'videoId' => (int) $this->getParameter('id'),
			'roleId' => (int) $this->getParameter('role'),
			'userId' => null,
		];

		// Find user by fullname and email
		$match = [];
		if ((preg_match('/(.*) <(.*)>/', $user, $match) !== 1) OR (count($match) !== 3)) {
			$this->payload->status = $this->translator->translate('alert.role_format_error');
			$this->getHttpResponse()->setCode(IResponse::S400_BAD_REQUEST);
			$this->sendPayload();
		}
		$users = $this->userManager->searchUser($match[1], $match[2]);

		if ($users->count() !== 1) {
			if ($users->count() === 0) {
				$this->payload->status = $this->translator->translate('alert.role_no_user_error');
			}
			else {
				Debugger::log("VideoPresenter: ambiguous user query '$user'", ILogger::ERROR);
				$this->payload->status = $this->translator->translate('alert.role_ambiguous_user_error');
			}
			$this->getHttpResponse()->setCode(IResponse::S400_BAD_REQUEST);
			$this->sendPayload();
		}

		$info['userId'] = (int) $users->fetch()->id;

		return $info;
	}

	/**
	 * @param string $query
	 * @throws Nette\Application\AbortException
	 */
	public function handleSearchUsr(string $query = ""): void
	{
		$this->payload->users = [];
		if (!empty($query)) {
			$users = $this->userManager->searchUser($query)->limit(10);
			foreach ($users as $user) {
				$this->payload->users[] = ['user' => $user->fullname .' <'. $user->email .'>'];
			}
		}

		$this->sendPayload();
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

		$this->template->people = $this->videoManager->getVideoPeople($id);
		$this->template->roles = $this->videoManager->getRoles();
		$this->template->searchUsrUrl = "'". $this->link('searchUsr!'). "&query=' + encodeURIComponent(query)";

		$this->template->resDatepicker = true;
		$this->template->resClockpicker = true;
		$this->template->resSelect = true;
		$this->template->resTinymce = true;
		$this->template->resFrmUsrName = true;
	}

	/**
	 * @secured
	 * @param int $id Video ID.
	 * @throws Nette\Application\AbortException
	 */
	public function handleAddLink(int $id): void
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
	public function handleDelLink(int $id): void
	{
		$this->payload->message = $this->videoManager->setShareLink($id, true);
		$this->payload->status = 'ok';

		$this->sendPayload();
	}

	/**
	 * @param int $id
	 * @throws Nette\Application\AbortException
	 */
	public function handleDel(int $id): void
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

	protected function createComponentEditVideoForm(): Form
	{
		$video = $this->videoManager->getVideoById((int) $this->getParameter('id'), true);
		$factory = new EditVideoFormFactory($this->videoManager, $this->userManager, $this->presenter, $this->translator, $video, $this->parameters['structure_tag']);
		return $factory->create();
	}

	protected function createComponentYoutubeUpload(): Form
	{
		$factory = new YoutubeUploadFormFactory($this, $this->translator, $this->tokenManager, $this->videoManager);
		return $factory->create();
	}
}
