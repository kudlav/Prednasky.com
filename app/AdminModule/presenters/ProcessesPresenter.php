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

		if (!$this->user->isInRole('admin')) {
			$this->error('Procesy a šablony smí spravovat pouze administrátor', Nette\Http\Response::S403_FORBIDDEN);
		}

		switch ($this->view) {

			case 'templates':
				$gridFactory = new ProcessesTemplatesGridFactory($this->tokenManager);
				$this->grid = $gridFactory->create($this, $this->translator);
				break;

			case 'tokens':
				$videoIds = array_keys($this->videoManager->getVideosByUser($this->user));
				$gridFactory = new ProcessesTokensGridFactory();
				$this->grid = $gridFactory->create($this->presenter, $this->translator);
				$this->grid->setDataSource($this->tokenManager->getTokensByVideo($videoIds));
				break;
		}
	}

	public function renderTokens(): void
	{
		$this->template->tab = 2;
		$this->template->resDatagrid = true;
	}

	public function renderTemplates(): void
	{
		$this->template->tab = 3;
		$this->template->resDatagrid = true;
	}

	public function createComponentDatagrid(): DataGrid
	{
		return $this->grid;
	}
}
