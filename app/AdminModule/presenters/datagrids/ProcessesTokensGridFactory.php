<?php
declare(strict_types=1);

namespace App\AdminModule\Datagrids;

use Nette;
use App\Model\TokenManager;
use Nette\Application\UI\Presenter;
use Ublaboo\DataGrid\DataGrid;
use Kdyby\Translation\Translator;


class ProcessesTokensGridFactory
{
	use Nette\SmartObject;

	public function __construct()
	{
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

		$grid->addColumnText('video', 'token.video')
			->setTemplateEscaping(FALSE)
			->setRenderer(function ($item) use ($presenter) {
				if ($item->video !== null) {
					return '<a href="'. $presenter->link('Video:edit', [(int) $item->video]) .'" class="btn btn-light">'. $item->ref(TokenManager::TOKEN_VIDEO)->name .'</a>';
				}
				return '';
			})
		;

		$grid->addColumnText('template', 'token.template')
			->setTemplateEscaping(FALSE)
			->setRenderer(function ($item) use ($presenter) {
				return '<a href="'. $presenter->link('Template:run', [(int) $item->template]) .'" class="btn btn-light">'. $item->ref(TokenManager::TOKEN_TEMPLATE)->name .'</a>';
			})
		;

		$grid->addColumnText('type', 'token.type')
			->setSortable()
			->setRenderer(function ($item) use ($translator) {
				$name = $item->ref(TokenManager::TOKEN_TYPE)->name;
				return $translator->translate('token_type.'. $name);
			})
		;

		$grid->addColumnDateTime('created', 'token.created')->setFormat('j. n. Y H:i:s')
			->setSortable()
		;

		$grid->addColumnDateTime('last_update', 'token.last_update')->setFormat('j. n. Y H:i:s')
			->setSortable()
		;

		$grid->addColumnText('pending_blocks', 'token.progress')
			->setTemplateEscaping(FALSE)
			->setAlign('center')
			->setRenderer(function ($item) {
				if ($item->pending_blocks == '') {
					$progress = 100;
				}
				else {
					$pending = count(explode(';', $item->pending_blocks));
					$all = count(explode(';', $item->ref(TokenManager::TOKEN_TEMPLATE)->blocks));
					$progress = 100 - round(100 * $pending / $all);
				}

				switch ($item->ref(TokenManager::TOKEN_STATE)->name) {
					case TokenManager::STATE_DONE:
						$state = 'bg-success';
						break;

					case TokenManager::STATE_ERROR:
						$state = 'bg-danger';
						break;

					default:
						$state = 'progress-bar-animated';
				}

				return '<div class="progress"><div class="progress-bar progress-bar-striped ' . $state . '" role="progressbar" style="width:'. $progress .'%" aria-valuenow="'. $progress .'" aria-valuemin="0" aria-valuemax="100">'. $progress .'&nbsp;%</div></div>';
			})
		;

		$grid->addColumnText('state', 'token.state')
			->setSortable()
			->setRenderer(function ($item) use ($translator) {
				$name = $item->ref(TokenManager::TOKEN_STATE)->name;
				return $translator->translate('token_state.'. $name);
			})
		;

		$grid->setTranslator($translator);

		return $grid;
	}
}
