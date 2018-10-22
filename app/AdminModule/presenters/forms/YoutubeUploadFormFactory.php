<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use Nette\Utils\ArrayHash;
use App\Model\TokenManager;
use App\Model\VideoManager;


class YoutubeUploadFormFactory
{
	use Nette\SmartObject;

	/**
	 * @var Presenter $presenter
	 * @var ITranslator $translator
	 * @var TokenManager $tokenManager
	 * @var VideoManager $videoManager
	 */
	private $presenter, $translator, $template, $tokenManager, $videoManager;

	public function __construct(Presenter $presenter, ITranslator $translator, TokenManager $tokenManager, VideoManager $videoManager)
	{
		$this->presenter = $presenter;
		$this->translator = $translator;
		$this->tokenManager = $tokenManager;
		$this->videoManager = $videoManager;
	}

	public function create(): Form
	{
		$form = new Form;

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = null;
		$renderer->wrappers['pair']['container'] = 'div class="row form-group pt-2"';
		$renderer->wrappers['pair']['.error'] = 'has-error';
		$renderer->wrappers['control']['container'] = 'div class=col-sm-10';
		$renderer->wrappers['label']['container'] = 'div class="col-sm-2 control-label"';
		$renderer->wrappers['control']['description'] = 'span class=help-block';
		$renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';
		$form->getElementPrototype()->class('form-horizontal');

		$form->setTranslator($this->translator);

		$form->addText('opt_input_url', 'URL')
			->setRequired('form.url_empty')
			->setAttribute('class', 'form-control')
		;

		$form->addSubmit('save', 'form.upload')
			->setAttribute('class', 'btn btn-primary')
		;

		$form->onSuccess[] = [$this, 'onSuccess'];

		return $form;
	}

	public function onSuccess(Form $form, ArrayHash $values): void
	{
		$videoID = $this->videoManager->newVideo($this->presenter->user);

		$templateValues = $this->tokenManager->getTokenDefaults();
		$templateValues['opt_input_url'] = $values['opt_input_url'];

		if ($this->tokenManager->submitToken($this->tokenManager->getTemplateByName('config_youtube_downloader.ini'), $templateValues, $videoID) === null) {
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
