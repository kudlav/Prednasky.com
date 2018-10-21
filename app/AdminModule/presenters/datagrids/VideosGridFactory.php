<?php
declare(strict_types=1);

namespace App\AdminModule\Datagrids;

use Nette;
use App\Model\VideoManager;
use App\Model\FileManager;
use Nette\Application\UI\Presenter;
use Ublaboo\DataGrid\DataGrid;
use Kdyby\Translation\Translator;
use Ublaboo\NetteDatabaseDataSource\NetteDatabaseDataSource;


class VideosGridFactory
{
	use Nette\SmartObject;

	/**
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 * @var string $urlDataExport
	 * @var array $structureTags
	 */
	private $videoManager, $fileManager, $urlDataExport, $structureTags;

	public function __construct(VideoManager $videoManager, FileManager $fileManager, string $urlDataExport, array $structureTags)
	{
		$this->videoManager = $videoManager;
		$this->fileManager = $fileManager;
		$this->urlDataExport = $urlDataExport;
		$this->structureTags = $structureTags;
	}

	/**
	 * @param Presenter $presenter
	 * @param Translator $translator
	 * @param NetteDatabaseDataSource $dataSource
	 * @param bool $actionView
	 * @return DataGrid
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 */
	public function create(Presenter $presenter, Translator $translator, NetteDatabaseDataSource $dataSource, bool $actionView=false): DataGrid
	{
		$grid = new DataGrid($presenter, 'datagrid');

		$grid->setTranslator($translator);
		$grid->setDataSource($dataSource);

		$grid->addColumnText(null, '')
			->setTemplateEscaping(FALSE)
			->setAlign('center')
			->setRenderer(function ($item) {
				$thumbnail = $this->fileManager->getVideoThumbnail($item->id);
				if ($thumbnail !== null) {
					return '<img style="height:2rem" src="'. $this->urlDataExport .'/'. $thumbnail->path .'"></div>';
				}
				return '<i class="fa fa-film text-muted fa-lg d-block py-1"></i>';
			});

		$grid->addColumnText('name', 'video.name')
			->setSortable()
		;

		$grid->addColumnDateTime('created', 'video.created')->setFormat('j. n. Y H:i')
			->setSortable()
		;

		$grid->addColumnText('duration', 'video.duration')
			->setSortable()
			->setRenderer(function ($item) {
				return $item->duration != null ? gmdate("H:i:s", $item->duration) : null;
		});

		$grid->addColumnText('state', 'video.state')
			->setTemplateEscaping(FALSE)
			->setAlign('center')
			->setSortable()
			->setRenderer(function ($item) use ($translator) {
				$state = $item->state_name;
				if ($state == 'public') {
					return '<i class="fa fa-globe" title="'. ucfirst($translator->translate('video_state.public')) .'" aria-hidden="true"></i>';
				}
			elseif ($state == 'logged_in') {
					return '<i class="fa fa-users" title="'. ucfirst($translator->translate('video_state.logged_in')) .'" aria-hidden="true"></i>';
				}
				elseif ($state == 'private') {
					if ($item->public_link !== null) {
						return '<i class="fa fa-link fa-lg" title="'. ucfirst($translator->translate('video_state.private')) .'" aria-hidden="true"></i>';
					}
					return '<i class="fa fa-ban" title="'. ucfirst($translator->translate('video_state.private')) .'" aria-hidden="true"></i>';
				}
				return '';
			});

		foreach ($this->structureTags as $structureTag) {
			$grid->addColumnText($structureTag, 'config.'.$structureTag)
				->setSortable()
			;
		}

		$grid->addAction('edit', '', 'Video:edit')
			->setIcon('pencil')
			->setClass('btn btn-light')
		;

		if ($actionView) {
			$grid->addAction('view', '', ':Front:Video:default')
				->setIcon('play-circle-o')
				->setClass('btn btn-light')
			;
		}

		$grid->sort = ['created' => 'DESC'];

		return $grid;
	}
}
