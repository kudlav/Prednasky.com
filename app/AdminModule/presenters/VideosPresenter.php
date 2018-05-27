<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;


class VideosPresenter extends BasePresenter
{

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

	private function sharedTemplateValues(): void
	{
		$this->template->processingCnt = 0;
		$this->template->draftCnt = 0;
	}
}
