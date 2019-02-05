<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use Kdyby\Translation\Translator;
use Ublaboo\DataGrid\DataGrid;
use App\AdminModule\Datagrids\UsersGridFactory;
use App\Model\UserManager;


class UsersPresenter extends BasePresenter
{
	/**
	 * @var UserManager $userManager
	 * @var Translator $translator
	 */
	private $userManager, $translator;

	public function __construct(UserManager $userManager, Translator $translator)
	{
		parent::__construct();

		$this->userManager = $userManager;
		$this->translator = $translator;
	}

	public function startup(): void
	{
		parent::startup();

		if (!$this->user->isInRole('admin')) {
			$this->error('Spravovat uživatele smí pouze administrátor', Nette\Http\Response::S403_FORBIDDEN);
		}
	}

	public function renderDefault(): void
	{
		$this->template->resDatagrid = true;
	}

	/**
	 * @return DataGrid
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 * @throws \Ublaboo\DataGrid\Exception\DataGridColumnStatusException
	 */
	public function createComponentDatagrid(): DataGrid
	{
		$gridFactory = new UsersGridFactory($this->userManager);

		return $gridFactory->create($this, $this->translator);
	}
}
