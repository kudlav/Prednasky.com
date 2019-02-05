<?php
declare(strict_types=1);

namespace App\AdminModule\Datagrids;

use Nette;
use App\Model\UserManager;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Presenter;
use Nette\Utils\ArrayHash;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\NetteDatabaseDataSource\NetteDatabaseDataSource;


class UsersGridFactory
{
	use Nette\SmartObject;

	/**
	 * @var UserManager $userManager
	 */
	private $userManager;

	public function __construct(UserManager $userManager)
	{
		$this->userManager = $userManager;
	}

	/**
	 * @param Presenter $presenter
	 * @param Translator $translator
	 * @return DataGrid
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 * @throws \Ublaboo\DataGrid\Exception\DataGridColumnStatusException
	 */
	public function create(Presenter $presenter, Translator $translator): DataGrid
	{
		$grid = new DataGrid($presenter, 'datagrid');

		$grid->setTranslator($translator);
		$grid->setDataSource($this->userManager->getUsers());

		$grid->addColumnNumber('id', 'id')
			->setSortable()
		;

		$grid->addColumnText('CAS_id', 'login')
			->setFilterText()
		;

		$grid->addColumnText('fullname', 'fullname')
			->setFilterText()
		;

		$grid->addColumnText('email', 'email')
			->setFilterText()
		;

		$grid->addColumnDateTime('last_login', 'last login')
			->setSortable()
			->setFormat('j. n. Y H:i')
		;

		$right_group = $grid->addColumnStatus('right_group', 'right group')
			->addOption(0, 'config.disabled')
			->setClass('btn-light')
			->endOption()
			->addOption(1, 'config.guest')
			->setClass('btn-dark')
			->endOption()
			->addOption(2, 'config.student')
			->setClass('btn-success')
			->endOption()
			->addOption(3, 'config.teacher')
			->setClass('btn-warning')
			->endOption()
			->addOption(4, 'config.admin')
			->setClass('btn-danger')
			->endOption()
		;
		$right_group->setFilterSelect([
			'' => $translator->translate('ublaboo_datagrid.all'),
			0 => $translator->translate('config.disabled'),
			1 => $translator->translate('config.guest'),
			2 => $translator->translate('config.student'),
			3 => $translator->translate('config.teacher'),
			4 => $translator->translate('config.admin'),
		]);

		/**
		 * Right group select editing
		 */
		$right_group->onChange[] = function (int $id, string $value) use ($grid) {
			if ($value >= 0 || $value <= 4) {
				if ($this->userManager->updateUser($id, [UserManager::USER_RIGHT_GROUP => $value])) {
					$grid->redrawItem($id);
				}
			}
		};

		/**
		 * Big inline editing
		 */
		$grid->addInlineEdit()
			->onControlAdd[] = function($container) use ($translator) {
			$container->addText('fullname', '');
			$container->addText('email', '');
			$container->addSelect('right_group', '', [
				0 => $translator->translate('config.disabled'),
				1 => $translator->translate('config.guest'),
				2 => $translator->translate('config.student'),
				3 => $translator->translate('config.teacher'),
				4 => $translator->translate('config.admin'),
			]);
		};

		$grid->getInlineEdit()->onSetDefaults[] = function($container, $item) {
			$container->setDefaults([
				'fullname' => $item->fullname,
				'email' => $item->email,
				'right_group' => $item->right_group,
			]);
		};

		$grid->getInlineEdit()->onSubmit[] = function(int $id, ArrayHash $values) use ($presenter, $grid) {
			if ($values->right_group >= 0 || $values->right_group <= 4) { // Save new values
				$result = $this->userManager->updateUser($id, [
					UserManager::USER_FULLNAME => $values->fullname,
					UserManager::USER_EMAIL => $values->email,
					UserManager::USER_RIGHT_GROUP => $values->right_group
				]);
				if ($result) {
					$grid->redrawItem($id);
				}
			}
			$presenter->flashMessage('alert.save_failed', 'error');
		};

		$grid->sort = ['id' => 'DESC'];

		return $grid;
	}
}
