<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;


class DashboardMenu extends Control
{
	public function render(Presenter $presenter): void
	{
		$this->template->setFile(__DIR__.'/dashboardMenu.latte');

		switch ($presenter->name) {
			case 'Admin:Homepage':
				$this->template->videos = true;
				//$this->template->lectures = true;
				//$this->template->files = true;
				//$this->template->processes = true;
				break;
		}

		$this->template->render();
	}
}
