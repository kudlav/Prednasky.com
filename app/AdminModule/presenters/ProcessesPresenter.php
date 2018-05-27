<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;


class ProcessesPresenter extends BasePresenter
{

	public function renderProcesses(): void
	{
		$this->sharedTemplateValues();
		$this->template->tab = 1;
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
}
