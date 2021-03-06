<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\ITranslator;
use Nette\Utils\ArrayHash;
use App\Model\TokenManager;
use App\Model\VideoManager;


class RunTemplateFormFactory
{
	use Nette\SmartObject;

	/**
	 * @var Presenter $presenter
	 * @var ITranslator $translator
	 * @var ActiveRow $template
	 * @var TokenManager $tokenManager
	 * @var VideoManager $videoManager
	 */
	private $presenter, $translator, $template, $tokenManager, $videoManager;

	public function __construct(Presenter $presenter, ITranslator $translator, ActiveRow $template, TokenManager $tokenManager, VideoManager $videoManager)
	{
		$this->presenter = $presenter;
		$this->translator = $translator;
		$this->template = $template;
		$this->tokenManager = $tokenManager;
		$this->videoManager = $videoManager;
	}

	public function create(): Form
	{
		$vars = $this->tokenManager->getTemplateVariables((string) $this->template->name);

		$defaultValues = $this->tokenManager->getTokenDefaults();

		$form = new Form;

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = null;
		$renderer->wrappers['pair']['container'] = 'div class="row form-group"';
		$renderer->wrappers['pair']['.error'] = 'has-error';
		$renderer->wrappers['control']['container'] = 'div class=col-sm-9';
		$renderer->wrappers['label']['container'] = 'div class="col-sm-3 control-label"';
		$renderer->wrappers['control']['description'] = 'span class=help-block';
		$renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';
		$form->getElementPrototype()->class('form-horizontal');

		$form->setTranslator($this->translator);

		foreach ($vars as $variable) {
			if (in_array($variable, ['job_id', 'public_datadir', 'private_datadir']) || in_array($variable, array_keys($defaultValues)) || $form->getComponent($variable, false) !== null) {
				continue;
			}

			$control = $form->addText($variable, $variable)
				->setRequired()
				->setAttribute('class', 'form-control')
			;

			if (isset($defaultValues[$variable])) {
				$control->setDefaultValue($defaultValues[$variable]);
			}
		}

		$form->addSubmit('save', 'form.run')
			->setAttribute('class', 'btn btn-primary')
		;

		$form->onSuccess[] = [$this, 'onSuccess'];

		return $form;
	}

	public function onSuccess(Form $form, ArrayHash $values): void
	{
		$videoID = $this->videoManager->newVideo($this->presenter->user);

		$allValues = array_merge($this->tokenManager->getTokenDefaults(), (array) $values);

		if ($this->tokenManager->submitToken($this->template, $allValues, $videoID) === null) {
			try {
				$this->videoManager->removeVideo($videoID);
			} catch (\Exception $e) {}
			$this->presenter->flashMessage('alert.run_task_failed', 'danger');
		}
		else {
			$this->presenter->flashMessage('alert.run_task_ok', 'success');
			$this->presenter->redirect('Video:edit', $videoID);
		}
	}

}
