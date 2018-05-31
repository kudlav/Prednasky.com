<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use App\Model\FileManager;
use Kdyby\Translation\Translator;
use App\Model\VideoManager;
use Ublaboo\DataGrid\DataGrid;
use App\AdminModule\Datagrids\VideosPublishedGridFactory;


class VideosPresenter extends BasePresenter
{
	/**
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 * @var Translator $translator
	 * @var DataGrid $grid
	 */
	private $videoManager, $fileManager, $translator, $grid;

	public function __construct(VideoManager $videoManager, FileManager $fileManager, Translator $translator)
	{
		$this->videoManager = $videoManager;
		$this->fileManager = $fileManager;
		$this->translator = $translator;
	}

	public function startup(): void
	{
		parent::startup();

		switch ($this->view) {

			case 'published':
				$gridFactory = new VideosPublishedGridFactory($this->videoManager, $this->fileManager, $this->parameters['paths']['url_data_export']);
				$this->grid = $gridFactory->create($this);
				break;

		}

		//$this->grid->setTranslator($this->translator);
	}

	public function renderPublished(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 1;
	}

	public function renderDrafts(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 2;
	}

	public function renderProcessing(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 3;
	}

	public function createComponentDatagrid($name): DataGrid
	{
		return $this->grid;
	}

	private function sharedTemplateValues(): void
	{
		$this->template->processingCnt = 0;
		$this->template->draftCnt = 0;
	}
}
