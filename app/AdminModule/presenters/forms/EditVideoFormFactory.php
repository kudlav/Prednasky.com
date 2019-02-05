<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use Nette;
use Nette\Utils\ArrayHash;
use Nette\Database\Table\ActiveRow;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use App\Model\VideoManager;
use App\Model\UserManager;


class EditVideoFormFactory
{
	use Nette\SmartObject;

	/**
	 * @var VideoManager $videoManager
	 * @var UserManager $userManager
	 * @var Presenter $presenter
	 * @var ITranslator $translator
	 * @var ActiveRow $video
	 * @var array $structureTags
	 */
	private $videoManager, $userManager, $presenter, $translator, $video, $structureTags;

	public function __construct(VideoManager $videoManager, UserManager $userManager, Presenter $presenter, ITranslator $translator, ActiveRow $video, array $structureTags)
	{
		$this->videoManager = $videoManager;
		$this->userManager = $userManager;
		$this->presenter = $presenter;
		$this->translator = $translator;
		$this->video = $video;
		$this->structureTags = $structureTags;
	}

	/**
	 * @return Form
	 */
	public function create(): Form
	{
		$form = new Form;

		$form->setTranslator($this->translator);

		$form->addText('title', 'video.name')
			->setDefaultValue($this->video->name)
			->setRequired('form.video_empty_name')
			->addRule(Form::MAX_LENGTH, 'form.video_long_name', 200)
			->setAttribute('placeholder', 'video.name_placeholder')
			->setAttribute('class', 'form-control')
		;

		$date = null;
		if (isset($this->video->record_date)) {
			$date = $this->video->record_date->format('j. n. Y');
		}
		$form->addText('date', 'video.date')
			->setDefaultValue($date)
			->setAttribute('placeholder', 'form.date_format')
			->setAttribute('class', 'form-control datepicker')
		;

		$time = null;
		if (isset($this->video->record_time_begin)) {
			$time = $this->video->record_time_begin->h . sprintf(':%02d', $this->video->record_time_begin->i);
		}
		$form->addText('record_time_begin', 'video.time')
			->setDefaultValue($time)
			->setAttribute('placeholder', 'form.time_format')
			->setAttribute('class', 'form-control clockpicker')
		;

		$time = null;
		if (isset($this->video->record_time_end)) {
			$time = $this->video->record_time_end->h . sprintf(':%02d', $this->video->record_time_end->i);
		}
		$form->addText('record_time_end')
			->setDefaultValue($time)
			->setAttribute('placeholder', 'form.time_format')
			->setAttribute('class', 'form-control clockpicker')
		;

		$visibilityValues = $this->videoManager->getStates();
		foreach ($visibilityValues as $id => $value) {
			$visibilityValues[$id] = 'video_state.' . $value;
		}

		$form->addSelect('visibility', 'video.state', $visibilityValues)
			->setDefaultValue($this->video->state)
			->setRequired('form.video_empty_visibility')
			->setAttribute('class', 'custom-select')
		;

		$form->addTextArea('abstract', 'video.abstract')
			->setDefaultValue($this->video->abstract)
			->setAttribute('rows', '6')
			->setAttribute('class', 'form-control tinymce')
		;

		foreach ($this->structureTags as $tag) {
			$tagRow = $this->videoManager->getVideoTagValue((int) $this->video->id, $tag);
			$form->AddSelect($tag, $tag, $this->videoManager->getTagValues($tag))
				->setDefaultValue($tagRow!==null && $tagRow->value!== null ? $tagRow->id : null)
				->setPrompt($this->translator->translate('form.start_typing'))
				->setTranslator(null)
				->setAttribute('class', 'form-control select2')
			;
		}

		$form->addSubmit('save', 'form.save')
			->setAttribute('class', 'btn btn-primary')
		;

		$form->onSuccess[] = [$this, 'onSuccess'];

		return $form;
	}

	public function onSuccess(Form $form, ArrayHash $values): void
	{
		$date = null;
		$match = [];
		if (isset($values->date) && preg_match('/(\d\d?).\s?(\d\d?).\s?(\d{4})/', $values->date, $match)) {
			$date = "$match[3]-$match[2]-$match[1]";
		}

		$record_time_begin =  null;
		if (isset($values->record_time_begin) && $values->record_time_begin !== "") {
			$record_time_begin = $values->record_time_begin;
		}
		$record_time_end =  null;
		if (isset($values->record_time_end) && $values->record_time_end !== "") {
			$record_time_end = $values->record_time_end;
		}

		$data = [
			VideoManager::VIDEO_NAME => $values->title,
			VideoManager::VIDEO_STATE => $values->visibility,
			VideoManager::VIDEO_RECORD_DATE => $date,
			VideoManager::VIDEO_RECORD_BEGIN => $record_time_begin,
			VideoManager::VIDEO_RECORD_END => $record_time_end,
			VideoManager::VIDEO_ABSTRACT => $values->abstract,
		];

		if ($this->video->published === null && $values->visibility !== 1) {
			$data[VideoManager::VIDEO_PUBLISHED] = date('Y-m-d H:i:s');
		}

		try {
			$this->videoManager->updateVideo((int)$this->video->id, $data);
		}
		catch (\PDOException $e) {
			$this->presenter->flashMessage('alert.save_failed', 'danger');
			return;
		}

		foreach ($this->structureTags as $tag) {
			$tagId = $values->offsetGet($tag) ?? $this->videoManager->getTag($tag, null)->id;

			$result = $this->videoManager->setVideoTagValue((int) $this->video->id, $tag, (int) $tagId);
			if ($result === false) {
				$translatedTag = $this->translator->translate('config.'. $tag);
				$this->presenter->flashMessage($this->translator->translate("alert.video_tag_failed", [
					'name' => $translatedTag,
					'value' => $tagId,
				]), 'warning');
			}
		}

		$this->presenter->flashMessage('alert.save_ok', 'success');

		$this->presenter->redirect('Videos:');
	}

}
