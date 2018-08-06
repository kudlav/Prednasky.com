<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\TokenManager;
use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\ITranslator;
use Nette\Utils\ArrayHash;


class EditTemplateFormFactory
{
	use Nette\SmartObject;

	/**
	 * @var Presenter $presenter
	 * @var ITranslator $translator
	 * @var ActiveRow $template
	 * @var TokenManager $tokenManager
	 */
	private $presenter, $translator, $template, $tokenManager;

	public function __construct(Presenter $presenter, ITranslator $translator, ActiveRow $template, TokenManager $tokenManager)
	{
		$this->presenter = $presenter;
		$this->translator = $translator;
		$this->template = $template;
		$this->tokenManager = $tokenManager;
	}

	public function create(): Form
	{

		$templateCode =  $this->tokenManager->getTemplateCode((string) $this->template->name);

		$form = new Form;

		$form->setTranslator($this->translator);

		$form->addText('name', 'Name')
			->setDefaultValue($this->template->name)
			->setRequired('form.template_empty_name')
			->setAttribute('placeholder', 'Template name')
			->setAttribute('class', 'form-control')
		;

		$form->addTextArea('description', 'Description')
			->setDefaultValue($this->template->description)
			->setAttribute('class', 'form-control')
		;

		$form->addTextArea('code', 'Code')
			->setDefaultValue($templateCode)
			->setAttribute('rows', '15')
			->setAttribute('class', 'form-control code')
		;

		$form->addText('blocks', 'Blocks')
			->setDefaultValue($this->template->blocks)
			->setAttribute('placeholder', 'block1;block2;finish')
			->setAttribute('class', 'form-control')
		;

		$form->addSubmit('save', 'form.save')
			->setAttribute('class', 'btn btn-primary')
		;

		$form->onSuccess[] = [$this, 'onSuccess'];

		return $form;
	}

	public function onSuccess(Form $form, ArrayHash $values): void
	{

		$data = [
			TokenManager::TEMPLATE_NAME => $values->name,
			TokenManager::TEMPLATE_BLOCKS => $values->blocks,
			TokenManager::TEMPLATE_DESCRIPTION => $values->description
		];

		if ($this->tokenManager->updateTemplate((int) $this->template->id, $data, $values->code)) {
			$this->presenter->flashMessage('alert.save_ok', 'success');
			$this->presenter->redirect('Processes:templates');
		}
		else {
			$this->presenter->flashMessage('alert.save_failed', 'danger');
		}


	}

}
