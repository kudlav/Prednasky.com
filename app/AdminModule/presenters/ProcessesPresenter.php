<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\AdminModule\Datagrids\ProcessesTokensGridFactory;
use App\Model\UserManager;
use App\Model\VideoManager;
use Nette;
use Kdyby\Translation\Translator;
use App\AdminModule\Datagrids\ProcessesTemplatesGridFactory;
use Ublaboo\DataGrid\DataGrid;
use App\Model\TokenManager;


class ProcessesPresenter extends BasePresenter
{

	/**
	 * @var TokenManager $tokenManager
	 * @var UserManager $userManager
	 * @var VideoManager $videoManager
	 * @var Translator $translator
	 * @var DataGrid $grid
	 */
	private $tokenManager, $userManager , $videoManager, $translator, $grid;

	public function __construct(TokenManager $tokenManager, UserManager $userManager, VideoManager $videoManager, Translator $translator)
	{
		$this->tokenManager = $tokenManager;
		$this->userManager = $userManager;
		$this->videoManager = $videoManager;
		$this->translator = $translator;
	}

	public function startup(): void
	{
		parent::startup();

		switch ($this->view) {

			case 'templates':
				$gridFactory = new ProcessesTemplatesGridFactory($this->tokenManager);
				$this->grid = $gridFactory->create($this, $this->translator);
				break;

			case 'tokens':
				$courses = $this->userManager->getUserCourses((int) $this->user->id);
				$courses = $this->userManager->formatUserCoursesSelect($courses, $this->parameters['structure_tag']);
				$videoIds = [];
				foreach ($courses as $ids => $names) {
					$videoIds = array_merge($videoIds, $this->videoManager->getVideosByTag(explode('-', $ids))->fetchPairs(null, VideoManager::VIDEO_ID));
				}
				$gridFactory = new ProcessesTokensGridFactory();
				$this->grid = $gridFactory->create($this->presenter, $this->translator);
				$this->grid->setDataSource($this->tokenManager->getTokensByVideo($videoIds));
				break;
		}
	}

	public function renderTokens(): void
	{
		$this->template->tab = 2;
	}

	public function renderTemplates(): void
	{
		$this->template->tab = 3;
	}

	public function createComponentDatagrid(): DataGrid
	{
		return $this->grid;
	}
}
