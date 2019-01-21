<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use Kdyby\Translation\Translator;
use Nette\Database\Table\Selection;
use Ublaboo\DataGrid\DataGrid;
use App\AdminModule\Datagrids\VideosGridFactory;
use App\Model\UserManager;
use App\Model\VideoManager;
use App\Model\FileManager;
use Ublaboo\NetteDatabaseDataSource\NetteDatabaseDataSource;


class VideosPresenter extends BasePresenter
{
	/**
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 * @var UserManager $userManager
	 * @var Translator $translator
	 * @var NetteDatabaseDataSource $videos
	 * @var DataGrid $grid
	 */
	private $videoManager, $fileManager, $userManager, $translator, $grid, $videos;

	public function __construct(VideoManager $videoManager, FileManager $fileManager, UserManager $userManager, Translator $translator)
	{
		parent::__construct();

		$this->videoManager = $videoManager;
		$this->fileManager = $fileManager;
		$this->userManager = $userManager;
		$this->translator = $translator;
	}

	public function startup(): void
	{
		parent::startup();

		if (!in_array($this->view, ['published', 'drafts', 'processing'])) {
			$this->redirect('Videos:published');
		}

		$gridFactory = new VideosGridFactory($this->videoManager, $this->fileManager, $this->parameters['paths']['url_data_export'], $this->parameters['structure_tag']);

		$this->videos = $this->videoManager->getVideosByUser($this->user, $this->view);

		if ($this->view == 'published') {
			$this->grid = $gridFactory->create($this, $this->translator, $this->videos, true);
		}
		else {
			$this->grid = $gridFactory->create($this, $this->translator, $this->videos, false);
		}
	}

	public function renderPublished(): void
	{
		$this->sharedTemplateValues(1);
	}

	public function renderDrafts(): void
	{
		$this->template->draftCnt = count($this->videos);
		$this->sharedTemplateValues(2);
	}

	public function renderProcessing(): void
	{
		$this->template->processingCnt = count($this->videos);
		$this->sharedTemplateValues(3);
	}

	public function createComponentDatagrid($name): DataGrid
	{
		return $this->grid;
	}

	private function sharedTemplateValues(int $tab): void
	{
		$this->template->resDatagrid = true;

		// tab
		$this->template->tab = $tab;

		// draftCnt
		if (!isset($this->template->draftCnt)) {
			$this->template->draftCnt = count($this->videoManager->getVideosByUser($this->user, 'drafts'));
		}

		// processingCnt
		if (!isset($this->template->processingCnt)) {
			$this->template->processingCnt = count($this->videoManager->getVideosByUser($this->user, 'processing'));
		}

	}
}
