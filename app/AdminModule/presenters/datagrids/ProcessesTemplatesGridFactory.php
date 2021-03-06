<?php
declare(strict_types=1);

namespace App\AdminModule\Datagrids;

use Nette;
use App\Model\TokenManager;
use Nette\Application\UI\Presenter;
use Ublaboo\DataGrid\DataGrid;
use Kdyby\Translation\Translator;


class ProcessesTemplatesGridFactory
{
	use Nette\SmartObject;

	/**
	 * @var TokenManager $tokenManager
	 */
	private $tokenManager;

	public function __construct(TokenManager $tokenManager)
	{
		$this->tokenManager = $tokenManager;
	}

	/**
	 * @param Presenter $presenter
	 * @param Translator $translator
	 * @return DataGrid
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 */
	public function create(Presenter $presenter, Translator $translator): DataGrid
	{
		$grid = new DataGrid($presenter, 'datagrid');

		$grid->setDataSource($this->tokenManager->getTemplates());

		$grid->setTranslator($translator);

		$grid->addColumnText('name', 'template.name');

		$grid->addColumnText('description', 'template.description');

		if ($presenter->user->isInRole('admin')) {
		$grid->addAction('edit', 'form.edit', 'Template:edit')
			->setIcon('pencil')
			->setClass('btn btn-light');
		}

		$grid->addAction('Run', 'form.run', 'Template:run')
			->setIcon('play')
			->setClass('btn btn-light');

		return $grid;
	}
}
