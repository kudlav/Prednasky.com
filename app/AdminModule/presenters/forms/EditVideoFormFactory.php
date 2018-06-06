<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\VideoManager;
use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Kdyby\Translation\Translator;
use Nette\Database\Table\ActiveRow;


class EditVideoFormFactory
{
	use Nette\SmartObject;

	/**
	 * @var VideoManager $videoManager
	 * @var Presenter $presenter
	 * @var Translator $translator
	 */
	private $videoManager, $presenter, $translator;

	public function __construct(VideoManager $videoManager, Presenter $presenter, Translator $translator)
	{
		$this->videoManager = $videoManager;
		$this->presenter = $presenter;
		$this->translator = $translator;
	}

	public function create(ActiveRow $video): Form
	{
		$form = new Form;

		$form->setTranslator($this->translator);

		$form->addText('title', 'Title')
			->setDefaultValue($video->name)
			->setAttribute('placeholder', 'Video title')
			->setAttribute('class', 'form-control')
		;

		$record_begin = null;
		$time_begin = null;
		if ($video->record_begin !== null) {
			$record_begin = $video->record_begin->format('j. n. Y');
			$time_begin = $video->record_begin->format('H:i');
		}
		$form->addText('record_date_start', 'Recording start')
			->setDefaultValue($record_begin)
			->setAttribute('placeholder', 'dd. mm. rrrr')
			->setAttribute('data-date-format','d. m. yyyy')
			->setAttribute('data-provide', 'datepicker')
			->setAttribute('data-date-orientation', 'bottom')
			->setAttribute('data-date-today-highlight', 'true')
			->setAttribute('data-date-autoclose', 'true')
			->setAttribute('class', 'form-control')
		;

		$form->addText('record_time_start')
			->setDefaultValue($time_begin)
			->setAttribute('type', 'time')
			->setAttribute('class', 'form-control clockpicker')
		;

		$record_end = null;
		$time_end = null;
		if ($video->record_end !== null) {
			$record_end = $video->record_end->format('j. n. Y');
			$time_end = $video->record_end->format('H:i');
		}
		$form->addText('record_date_end', 'Recording end')
			->setDefaultValue($record_end)
			->setAttribute('placeholder', 'dd. mm. rrrr')
			->setAttribute('data-date-format','d. m. yyyy')
			->setAttribute('data-provide', 'datepicker')
			->setAttribute('data-date-orientation', 'bottom')
			->setAttribute('data-date-today-highlight', 'true')
			->setAttribute('data-date-autoclose', 'true')
			->setAttribute('class', 'form-control')
		;

		$form->addText('record_time_end')
			->setDefaultValue($time_end)
			->setAttribute('type', 'time')
			->setAttribute('class', 'form-control clockpicker')
		;

		$visibilityValues = $this->videoManager->getDoneStates();
		foreach ($visibilityValues as $id => $value) {
			$visibilityValues[$id] = 'video_state.' . $value;
		}

		$form->addSelect('visibility', 'Visibility', $visibilityValues)
			->setDefaultValue($video->state)
			->setAttribute('class', 'custom-select')
		;

		$form->addTextArea('abstract', 'Abstract')
			->setAttribute('class', 'form-control')
		;

		return $form;
	}

	public function onSuccess(Form $form, $values): void
	{
	}

}
