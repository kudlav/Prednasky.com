<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use Nette;
use Nette\Utils\ArrayHash;
use Nette\Database\Table\ActiveRow;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Kdyby\Translation\Translator;
use App\Model\VideoManager;


class EditVideoFormFactory
{
	use Nette\SmartObject;

	/**
	 * @var VideoManager $videoManager
	 * @var Presenter $presenter
	 * @var Translator $translator
	 * @var ActiveRow $video
	 * @var array $structureTags
	 */
	private $videoManager, $presenter, $translator, $video, $structureTags;

	public function __construct(VideoManager $videoManager, Presenter $presenter, Translator $translator, ActiveRow $video, array $structureTags)
	{
		$this->videoManager = $videoManager;
		$this->presenter = $presenter;
		$this->translator = $translator;
		$this->video = $video;
		$this->structureTags = $structureTags;
	}

	public function create(): Form
	{
		$form = new Form;

		$form->setTranslator($this->translator);

		$form->addText('title', 'Title')
			->setDefaultValue($this->video->name)
			->setRequired('form.video_empty_name')
			->setAttribute('placeholder', 'Video title')
			->setAttribute('class', 'form-control')
		;

		$record_begin = null;
		$time_begin = null;
		if ($this->video->record_begin !== null) {
			$record_begin = $this->video->record_begin->format('j. n. Y');
			$time_begin = $this->video->record_begin->format('H:i');
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
		if ($this->video->record_end !== null) {
			$record_end = $this->video->record_end->format('j. n. Y');
			$time_end = $this->video->record_end->format('H:i');
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

		$visibilityValues = $this->videoManager->getStates();
		foreach ($visibilityValues as $id => $value) {
			$visibilityValues[$id] = 'video_state.' . $value;
		}

		$form->addSelect('visibility', 'Visibility', $visibilityValues)
			->setDefaultValue($this->video->state)
			->setAttribute('class', 'custom-select')
		;

		$form->addTextArea('abstract', 'Abstract')
			->setDefaultValue($this->video->abstract)
			->setAttribute('rows', '6')
			->setAttribute('class', 'form-control')
		;

		$first = true;
		foreach ($this->structureTags as $tag)
		{
			$tagRow = $this->videoManager->getVideoTagValue((int) $this->video->id, $tag);
			$input = $form->AddText($tag)
				->setDefaultValue($tagRow!==null ? $tagRow->value : null)
				->setAttribute('placeholder', 'Start typing...')
				->setAttribute('class', 'form-control')
			;

			if ($first) {
				$translatedTag = $this->translator->translate('config.'. $tag);
				$input->setRequired($this->translator->translate('form.video_empty_tag', ['tag' => $translatedTag]));
				$first = false;
			}
		}

		$form->addSubmit('save', 'Save')
			->setAttribute('class', 'btn btn-primary')
		;

		$form->onSuccess[] = [$this, 'onSuccess'];

		return $form;
	}

	public function onSuccess(Form $form, ArrayHash $values): void
	{
		$recordBegin = $values->record_date_start==="" ? null : \DateTime::createFromFormat('j. n. Y H:i', $values->record_date_start .' '. $values->record_time_start);
		$recordEnd = $values->record_date_end==="" ? null : \DateTime::createFromFormat('j. n. Y H:i', $values->record_date_end .' '. $values->record_time_end);

		$data = [
			VideoManager::VIDEO_NAME => $values->title,
			VideoManager::VIDEO_STATE => $values->visibility,
			VideoManager::VIDEO_RECORD_BEGIN => $recordBegin,
			VideoManager::VIDEO_RECORD_END => $recordEnd,
			VideoManager::VIDEO_ABSTRACT => $values->abstract,
		];

		$change = false;

		try {
			$this->videoManager->updateVideo((int)$this->video->id, $data);
		}
		catch (\PDOException $e) {
			$this->presenter->flashMessage('alert.save_failed', 'danger');
			return;
		}

		foreach ($this->structureTags as $tag) {
			if ($values->offsetExists($tag)) {
				$tagValue = $values->offsetGet($tag);
				if ($tagValue == "") {
					$tagValue = null;
				}
				$result = $this->videoManager->setVideoTagValue((int) $this->video->id, $tag, $tagValue);
				if ($result === false) {
					$translatedTag = $this->translator->translate('config.'. $tag);
					$this->presenter->flashMessage($this->translator->translate("alert.video_tag_failed", [
						'name' => $translatedTag,
						'value' => $tagValue,
					]), 'warning');
				}
			}
		}

		$this->presenter->flashMessage('alert.save_ok', 'success');

		$this->presenter->redirect('Videos:');
	}

}
