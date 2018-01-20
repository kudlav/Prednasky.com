<?php

namespace App\Presenters;

use Nette;
use App\Model\Parameters;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
	/** @persistent */
	public $locale;

	/** @var Parameters */
	protected $parameters;

	public function injectParameters(Parameters $parameters) {
		$this->parameters = $parameters->getParam();
	}
}
