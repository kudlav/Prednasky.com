<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use Kdyby\Translation\Translator;
use Ublaboo\DataGrid\DataGrid;
use App\AdminModule\Datagrids\VideosGridFactory;
use App\Model\UserManager;
use App\Model\VideoManager;
use App\Model\FileManager;


class VideosPresenter extends BasePresenter
{
	/**
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 * @var UserManager $userManager
	 * @var Translator $translator
	 * @var array $courses
	 * @var DataGrid $grid
	 */
	private $videoManager, $fileManager, $userManager, $translator, $courses, $grid;

	public function __construct(VideoManager $videoManager, FileManager $fileManager, UserManager $userManager, Translator $translator)
	{
		$this->videoManager = $videoManager;
		$this->fileManager = $fileManager;
		$this->userManager = $userManager;
		$this->translator = $translator;
	}

	public function startup(): void
	{
		parent::startup();

		$this->courses = $this->userManager->getUserCourses((int) $this->user->id);
		$this->courses = $this->userManager->formatUserCoursesSelect($this->courses, $this->parameters['structure_tag']);

		$gridFactory = new VideosGridFactory($this->videoManager, $this->fileManager, $this->parameters['paths']['url_data_export']);
		$this->grid = $gridFactory->create($this, $this->translator);
	}

	public function renderDefault(): void
	{
		$this->redirect('Videos:published');
	}

	public function renderPublished(string $course=''): void
	{
		$this->template->courses = $this->courses;

		if ($course != '') {
			$this->grid->setDataSource($this->videoManager->getVideosByTag(explode('-',$course), 'published'));
			$this->redrawControl('datagrid');
		}
		// Set default data source
		else {
			reset($this->template->courses);
			$tagIds = explode('-', key($this->template->courses));
			$this->grid->setDataSource($this->videoManager->getVideosByTag($tagIds, 'published'));
		}

		$this->sharedTemplateValues(1);
	}

	public function renderDrafts(): void
	{
		$this->template->draftCnt = 0;
		foreach ($this->courses as $ids => $names) {
			$videos = $this->videoManager->getVideosByTag(explode('-', $ids), 'draft');
			$this->grid->setDataSource($videos);
			$this->template->draftCnt += $videos->count();
		}

		$this->sharedTemplateValues(2);
	}

	public function renderProcessing(): void
	{
		$this->template->processingCnt = 0;
		foreach ($this->courses as $ids => $names) {
			$videos = $this->videoManager->getVideosByTag(explode('-', $ids), 'processing');
			$this->grid->setDataSource($videos);
			$this->template->processingCnt += $videos->count();
		}

		$this->sharedTemplateValues(3);
	}

	public function createComponentDatagrid($name): DataGrid
	{
		return $this->grid;
	}

	private function sharedTemplateValues(int $tab): void
	{
		// tab
		$this->template->tab = $tab;

		// draftCnt
		if (!isset($this->template->draftCnt)) {
			$this->template->draftCnt = 0;
			foreach ($this->courses as $ids => $names) {
				$this->template->draftCnt += $this->videoManager->getVideosByTag(explode('-', $ids), 'draft')->count();
			}
		}

		// processingCnt
		if (!isset($this->template->processingCnt)) {
			$this->template->processingCnt = 0;
			foreach ($this->courses as $ids => $names) {
				$this->template->processingCnt += $this->videoManager->getVideosByTag(explode('-', $ids), 'processing')->count();
			}
		}

	}
}
