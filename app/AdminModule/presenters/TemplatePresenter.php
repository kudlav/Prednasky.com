<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use Nette\Forms\Form;
use Kdyby\Translation\Translator;
use App\AdminModule\Forms\EditTemplateFormFactory;
use App\Model\TokenManager;


class TemplatePresenter extends BasePresenter
{
	/**
	 * @var TokenManager $tokenManager
	 * @var Translator $translator
	 */
	private $tokenManager, $translator;

	public function __construct(TokenManager $tokenManager, Translator $translator)
	{
		$this->tokenManager = $tokenManager;
		$this->translator = $translator;
	}

	public function renderEdit(int $id): void
	{
		$this->template->prevPage = $this->getHttpRequest()->getReferer() ?? $this->link('Processes:templates');
	}

	public function createComponentEditTemplateForm(): Form
	{
		$template = $this->tokenManager->getTemplateById((int) $this->getParameter('id'));
		if ($template === null) {
			$this->error('Šablona s id '. $this->getParameter('id') .' neexistuje!', Nette\Http\IResponse::S404_NOT_FOUND);
		}
		$factory = new EditTemplateFormFactory($this, $this->translator, $template, $this->tokenManager);
		return $factory->create();
	}

}
