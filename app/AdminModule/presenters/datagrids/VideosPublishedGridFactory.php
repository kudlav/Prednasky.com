<?php
declare(strict_types=1);

namespace App\AdminModule\Datagrids;

use Nette;
use App\Model\VideoManager;
use App\Model\FileManager;
use Nette\Application\UI\Presenter;
use Ublaboo\DataGrid\DataGrid;


class VideosPublishedGridFactory
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
	 * @return DataGrid
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 */
	public function create(Presenter $presenter): DataGrid
	{
		$grid = new DataGrid($presenter, 'datagrid');

		$grid->setDataSource($this->videoManager->getAllVideos('created DESC'));

		$grid->addColumnText(null, '')
			->setTemplateEscaping(FALSE)
			->setAlign('center')
			->setRenderer(function ($item) {
				$thumbnail = $this->fileManager->getVideoThumbnail($item->id);
				if ($thumbnail != null) {
					return '<img style="height:2rem" src="'. $this->urlDataExport .'/'. $thumbnail->ref(FileManager::TABLE_FILE)->path .'"></div>';
				}
				return '<i class="fa fa-film text-muted fa-lg d-block py-1"></i>';
			});

		$grid->addColumnText('name', 'Name');

		$grid->addColumnDateTime('created', 'Created')->setFormat('j. n. Y H:i');

		$grid->addColumnText('duration', 'Duration')->setRenderer(function ($item) {
			return $item->duration != null ? gmdate("H:i:s", $item->duration) : null;
		});

		$grid->addColumnText('state', 'State')
			->setTemplateEscaping(FALSE)
			->setAlign('center')
			->setRenderer(function ($item) {
				$state = $item->ref(VideoManager::VIDEO_STATE)->name;
				if (strpos($state, '_public') !== false) {
					return '<i class="fa fa-globe" aria-hidden="true"></i>';
				}
				elseif (strpos($state, '_logged_in') !== false) {
					return '<i class="fa fa-users" aria-hidden="true"></i>';
				}
				elseif (strpos($state, '_private') !== false) {
					if ($item->public_link != null) {
						return '<i class="fa fa-link fa-lg" aria-hidden="true"></i>';
					}
					return '<i class="fa fa-ban" aria-hidden="true"></i>';
				}
				return '';
			});

		$grid->addAction('edit', '', 'Video:edit')
			->setIcon('pencil')
			->setClass('btn btn-light');

		return $grid;
	}
}
