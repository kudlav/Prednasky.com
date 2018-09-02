<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use Kdyby\Translation\Translator;
use App\AdminModule\Datagrids\ProcessesTemplatesGridFactory;
use Ublaboo\DataGrid\DataGrid;
use App\Model\TokenManager;


class ProcessesPresenter extends BasePresenter
{

	/**
	 * @var TokenManager $tokenManager
	 * @var Translator $translator
	 * @var DataGrid $grid
	 */
	private $tokenManager, $translator, $grid;

	public function __construct(TokenManager $tokenManager, Translator $translator)
	{
		$this->tokenManager = $tokenManager;
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

		}
	}

	public function renderTokens(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 2;
	}

	public function renderTemplates(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 3;
	}

	private function sharedTemplateValues(): void
	{

	}

	public function createComponentDatagrid(): DataGrid
	{
		return $this->grid;
	}
}
