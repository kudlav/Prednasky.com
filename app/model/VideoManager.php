<?php

namespace App\Model;

use Nette;


class VideoManager
{
	use Nette\SmartObject;

	const
		TABLE_VIDEO = 'video',
		VIDEO_ID = 'id',
		VIDEO_NAME = 'name',
		VIDEO_CREATED = 'created',
		VIDEO_STATE = 'state',
		VIDEO_PUBLISHED = 'published',
		VIDEO_RECORD_BEGIN = 'record_begin',
		VIDEO_RECORD_END = 'record_end',
		VIDEO_DURATION = 'duration',
		VIDEO_ABSTRACT = 'abstract',
		VIDEO_PLANE_POINTS = 'plane_points',
		VIDEO_PLANE_WIDTH = 'plane_width',
		VIDEO_PUBLIC_LINK = 'plane_public_link',

		TABLE_TAG = 'tag',
		TAG_ID = 'id',
		TAG_NAME = 'name',
		TAG_VALUE = 'value',

		TABLE_VIDEO_TAG = 'video_has_tag'
	;

	/** @var Nette\Database\Context */
	private $parameters, $database;

	public function __construct($parameters, Nette\Database\Context $database)
	{
		$this->parameters = $parameters;
		$this->database = $database;
	}


	/**
	 * Insert video into database
	 *
	 * @param      string   $name          Name of video
	 * @param      integer  $state         State accordint to `video_state` table
	 * @param      integer  $record_begin  Timestamp when the recording was started
	 * @param      integer  $record_end    Timestamp when the recording was stopped
	 * @param      string   $abstract      Video abstract
	 *
	 * @return     integer  ID of new video or NULL
	 */
	public function newVideo(string $name="Unnamed", int $state=1, int $record_begin=NULL, int $record_end=NULL, string $abstract=NULL)
	{
		$row = $this->database->table(self::TABLE_VIDEO)->insert([
			self::VIDEO_NAME => $name,
			self::VIDEO_CREATED => date('Y-m-d H:i:s'),
			self::VIDEO_STATE => $state,
			self::VIDEO_RECORD_BEGIN => $record_begin,
			self::VIDEO_RECORD_END => $record_end,
			self::VIDEO_ABSTRACT => $abstract
		]);

		if ($row) {
			\Tracy\Debugger::log("VideoManager: Created video 'id':'".$row->id."'", \Tracy\ILogger::INFO);
			return $row->id;
		}
		\Tracy\Debugger::log("VideoManager: Unable to create video '".$name."'", \Tracy\ILogger::ERROR);
		return NULL;
	}

	/**
	 * Get all videos.
	 *
	 * @param string $orderBy The order of videos
	 *
	 * @return Nette\Database\Table\Selection All videos.
	 */
	public function getAllVideos(string $orderBy='id DESC')
	{
		return $this->database->table(self::TABLE_VIDEO)->order($orderBy);
	}

	/* Tags */

	public function getTagValues($tag)
	{
		return $this->database->table(self::TABLE_TAG)->where(self::TAG_NAME, $tag)->fetchPairs(self::TAG_ID, self::TAG_VALUE);
	}


	public function getNestedTagValues(array $path)
	{
		// RETURN: Top level - returning all values of root tag (fast)
		if (empty($path)) {
			$nestedTagValues['lvl'] = $this->parameters['required_tags'][0];
			$nestedTagValues['val'] = $this->getTagValues($this->parameters['required_tags'][0]);
			return $nestedTagValues;
		}

		$valuesId = [];
		$tagLevel = 0;
		$pathIndex = 0;

		// Get get list of required tags. Videos have to contain all of them.
		for ($tagLevel; $tagLevel<count($this->parameters['required_tags']); $tagLevel++) {
			$id = $this->issetTagValue($this->parameters['required_tags'][$tagLevel], $path[$pathIndex]);
			if ($id !== NULL) {
				$valuesId[] = $id;
				$pathIndex++;
				if (!isset($path[$pathIndex])) { // Done.
					break;
				}
			}
		}
		$tagLevel++;

		if (count($valuesId) != count($path)) { // Check for non existing path.
			return NULL;
		}

		// RETURN: The lowest level, no nested tags (fastest)
		if (!isset($this->parameters['required_tags'][$tagLevel])) {
			$nestedTagValues['val'] = [];
		}
		// RETURN: Return nested values containing some video (slow)
		else {
			$selection = $this->database->table(self::TABLE_VIDEO_TAG);
			foreach ($valuesId as $id) {
				$videosId = $selection->where('tag_id', $id)->fetchPairs(NULL, 'video_id'); // Get list of suitable videos
				$selection = $this->database->table(self::TABLE_VIDEO_TAG)->where('video_id', $videosId); // Get rows of suitable videos
			}
			while (empty($nestedTagValues['val']) && isset($this->parameters['required_tags'][$tagLevel])) { // While empty, try to go deeper.
				$nestedTagValues['lvl'] = $this->parameters['required_tags'][$tagLevel];
				$nestedTagValues['val'] = $this->database->table(self::TABLE_VIDEO_TAG)
					->where('video_id', $videosId)
					->select('tag.name AS name, tag.value AS value')
					->where('name', $this->parameters['required_tags'][$tagLevel])
					->fetchPairs(NULL, 'value')
				;
				$tagLevel++;
			}
		}

		return $nestedTagValues;
	}

	public function issetTagValue(string $tag, string $value)
	{
		$row = $this->database->table(self::TABLE_TAG)
			->where(self::TAG_NAME, $tag)
			->where(self::TAG_VALUE, $value)
			->fetch()
		;

		if ($row !== FALSE) {
			return $row->id;
		}
		return NULL;
	}
}
