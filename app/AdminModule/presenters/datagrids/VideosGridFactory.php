<?php
declare(strict_types=1);

namespace App\AdminModule\Datagrids;

use Nette;
use App\Model\VideoManager;
use App\Model\FileManager;
use Nette\Application\UI\Presenter;
use Ublaboo\DataGrid\DataGrid;
use Kdyby\Translation\Translator;


class VideosGridFactory
{
	use Nette\SmartObject;

	/**
	 * @var VideoManager $videoManager
	 * @var FileManager $fileManager
	 * @var string $urlDataExport
	 */
	private $videoManager, $fileManager, $urlDataExport;

	public function __construct(VideoManager $videoManager, FileManager $fileManager, string $urlDataExport)
	{
		$this->videoManager = $videoManager;
		$this->fileManager = $fileManager;
		$this->urlDataExport = $urlDataExport;
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

		$grid->setTranslator($translator);

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

		$grid->addColumnText('name', 'Name')
			->setSortable()
		;

		$grid->addColumnDateTime('created', 'Created')->setFormat('j. n. Y H:i')
			->setSortable()
		;

		$grid->addColumnText('duration', 'Duration')
			->setSortable()
			->setRenderer(function ($item) {
			return $item->duration != null ? gmdate("H:i:s", $item->duration) : null;
		});

		$grid->addColumnText('state', 'State')
			->setTemplateEscaping(FALSE)
			->setAlign('center')
			->setSortable()
			->setRenderer(function ($item) use ($translator) {
				$state = $item->ref(VideoManager::VIDEO_STATE)->name;
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

		$grid->addAction('edit', '', 'Video:edit')
			->setIcon('pencil')
			->setClass('btn btn-light')
		;

		$grid->sort = ['created' => 'DESC'];

		return $grid;
	}
}
