<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;


class LecturesPresenter extends BasePresenter
{

	public function renderAll(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 1;
	}

	public function renderUnused(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 2;
	}

	public function renderProcessing(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 3;
	}

	private function sharedTemplateValues(): void
	{
		$this->template->processingCnt = 0;
	}
}
