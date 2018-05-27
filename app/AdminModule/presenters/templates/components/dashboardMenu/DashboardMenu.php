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
			case 'Admin:Videos':
				$this->template->videos = true;
			break;

			case 'Admin:Processes':
				$this->template->processes = true;
				break;

			case 'Admin:Lectures':
				$this->template->lectures = true;
				break;

			case 'Admin:Files':
				$this->template->files = true;
				break;
		}

		$this->template->render();
	}
}
