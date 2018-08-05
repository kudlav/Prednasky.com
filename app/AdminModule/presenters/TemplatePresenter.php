<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette;
use Nette\Forms\Form;
use Kdyby\Translation\Translator;
use Nette\Database\Table\ActiveRow;
use App\AdminModule\Forms\EditTemplateFormFactory;
use App\AdminModule\Forms\RunTemplateFormFactory;
use App\Model\TokenManager;
use App\Model\VideoManager;


class TemplatePresenter extends BasePresenter
{
	/**
	 * @var TokenManager $tokenManager
	 * @var VideoManager $videoManager
	 * @var Translator $translator
	 * @var ActiveRow $templateRow
	 */
	private $tokenManager, $videoManager, $translator, $templateRow;

	public function __construct(TokenManager $tokenManager, VideoManager $videoManager, Translator $translator)
	{
		$this->tokenManager = $tokenManager;
		$this->videoManager = $videoManager;
		$this->translator = $translator;
	}

	public function renderEdit(int $id): void
	{
		if (!$this->user->isInRole('admin')) {
			$this->error('Upravovat šablony smí pouze administrátor', Nette\Http\Response::S403_FORBIDDEN);
		}
		$this->template->prevPage = $this->getHttpRequest()->getReferer() ?? $this->link('Processes:templates');
	}

	public function renderRun(int $id): void
	{
		$this->templateRow = $this->tokenManager->getTemplateById((int) $this->getParameter('id'));
		$this->template->prevPage = $this->getHttpRequest()->getReferer() ?? $this->link('Processes:templates');
		$this->template->templateName = $this->templateRow->name;
		$this->template->templateDesc = $this->templateRow->description;
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

	public function createComponentRunTemplateForm(): Form
	{
		if ($this->templateRow === null) {
			$this->error('Šablona s id '. $this->getParameter('id') .' neexistuje!', Nette\Http\IResponse::S404_NOT_FOUND);
		}
		$factory = new RunTemplateFormFactory($this, $this->translator, $this->templateRow, $this->tokenManager, $this->videoManager);
		return $factory->create();
	}

}
