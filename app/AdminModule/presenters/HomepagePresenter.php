<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;


class HomepagePresenter extends BasePresenter
{
	public function renderDefault(): void
	{
		$this->redirect('Videos:published');
	}
}
