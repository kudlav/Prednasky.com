<?php

namespace App\Model;

use Nette;


class VideoManager
{
	use Nette\SmartObject;

	const
		// Video table
		TABLE_VIDEO = 'video',
		VIDEO_ID = 'id',
		VIDEO_NAME = 'name',
		VIDEO_CREATED = 'created',
		VIDEO_STATE = 'state',
		VIDEO_PUBLISHED = 'published',
		VIDEO_RECORD_BEGIN = 'record_begin',
		VIDEO_RECORD_END = 'record_end',
		VIDEO_ABSTRACT = 'abstract',

		// Tag table
		TABLE_TAG = 'tag',
		TAG_ID = 'id',
		TAG_NAME = 'name',
		TAG_VALUE = 'value',

		// Video_has_tag table
		TABLE_VIDEO_TAG = 'video_has_tag',

		// Video_state table
		TABLE_VIDEO_STATE = 'video_state',
		STATE_ID = 'id',
		STATE_NAME = 'name'
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

	/**
	 * Get latest published videos.
	 *
	 * @param      integer  $limit     The limit of videos
	 * @param      boolean  $loggedIn  Include videos available after logging in.
	 *
	 * @return     Nette\Database\Table\Selection   Published videos.
	 */
	public function getPublishedVideos(int $limit=0, bool $loggedIn=FALSE)
	{
		$state = ['done_public'];
		if ($loggedIn) {
			$state[] = 'done_logged_in';
		}
		$state = $this->database->table(self::TABLE_VIDEO_STATE)
			->where(self::STATE_NAME, $state)
			->fetchPairs(NULL, self::STATE_ID)
		;

		$selection = $this->database->table(self::TABLE_VIDEO)
			->where(self::VIDEO_STATE, $state)
			->order(self::VIDEO_PUBLISHED.' DESC')
		;

		if ($limit>0) {
			return $selection->limit($limit);
		}
		return $selection;
	}

	/**
	 * Return selection of videos which doesn't contain any nested tag.
	 *
	 * @param      integer  $level     The level in range of config directive array.
	 * @param      array    $videosId  Videos identifier that will be check for nested tags.
	 *
	 * @return     <type>   The videos by tag level.
	 */
	public function getVideosByTagLevel(int $level, array $videosId=[])
	{
		$videos = $this->database->table(self::TABLE_VIDEO);

		for ($level; $level<count($this->parameters['required_tags']); $level++) { // If lower level, display all vidoeos
			$id = $this->issetTagValue($this->parameters['required_tags'][$level], NULL);
			$selection = $this->database->table(self::TABLE_VIDEO_TAG)->where('video_id', $videosId); // Get rows of suitable videos
			$videosId = $selection->where('tag_id', $id)->fetchPairs(NULL, 'video_id'); // Get list of suitable videos
		}
		return $videos->where(self::VIDEO_ID, $videosId);
	}

	/* TAGS */

	/**
	 * Get all values of specified tag.
	 *
	 * @param      string  $tag  The tag
	 *
	 * @return     array   Associative array: 'tag_id'=>'tag_value'.
	 */
	private function getTagValues(string $tag)
	{
		 $values = $this->database->table(self::TABLE_TAG)
			->where(self::TAG_NAME, $tag)
			->fetchPairs(self::TAG_ID, self::TAG_VALUE)
		;
		return array_filter($values);
	}

	/**
	 * Get subtags (nested tags) of current path.
	 *
	 * @param      array   $path   The current path (array of tagvalues).
	 *
	 * @return     array  Array containing 'lvl', 'val'
	 */
	public function getNestedTagValues(array $path)
	{
		// RETURN: Top level - returning all values of root tag (fast)
		if (empty($path)) {
			return [
				'lvl' => 0,
				'val' => $this->getTagValues($this->parameters['required_tags'][0]),
			];
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
				if (!isset($path[$pathIndex])) { // Done
					break;
				}
			}
		}
		$tagLevel++;

		if (count($valuesId) != count($path)) { // Check for non existing path
			return NULL;
		}

		// Return nested values containing some video (slow)
		$selection = $this->database->table(self::TABLE_VIDEO_TAG);
		foreach ($valuesId as $id) {
			$videosId = $selection->where('tag_id', $id)->fetchPairs(NULL, 'video_id'); // Get list of suitable videos
			$selection = $this->database->table(self::TABLE_VIDEO_TAG)->where('video_id', $videosId); // Get rows of suitable videos
		}
		$nestedTagValues['vid'] = $videosId;
		// The lowest level, no nested tags, false while condition
		$nestedTagValues['lvl'] = count($this->parameters['required_tags']);
		$nestedTagValues['val'] = [];
		// While empty, try to go deeper
		while (empty($nestedTagValues['val']) && isset($this->parameters['required_tags'][$tagLevel])) {
			$nestedTagValues['lvl'] = $tagLevel;
			$values = $this->database->table(self::TABLE_VIDEO_TAG)
				->where('video_id', $videosId)
				->select('tag.name AS name, tag.value AS value')
				->where('name', $this->parameters['required_tags'][$tagLevel])
				->fetchPairs(NULL, 'value')
			;
			$nestedTagValues['val'] = array_filter($values);
			$tagLevel++;
		}

		return $nestedTagValues;
	}

	/**
	 * Return id of row from `video_has_tag` table.
	 *
	 * @param      string  $tag    The tag name
	 * @param      string|NULL  $value  The value
	 *
	 * @return     int|NULL  ID of row, or NULL when combination of name and value does't exit.
	 */
	private function issetTagValue(string $tag, $value)
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
