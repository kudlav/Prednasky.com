<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;


class FilesPresenter extends BasePresenter
{

	public function renderAll(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 1;
	}

	public function renderAttachments(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 2;
	}

	public function renderUnused(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 3;
	}

	private function sharedTemplateValues(): void
	{
		$this->template->unusedCnt = 0;
	}
}
