<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;


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
		VIDEO_DURATION = 'duration',
		VIDEO_ABSTRACT = 'abstract',

		// Tag table
		TABLE_TAG = 'tag',
		TAG_ID = 'id',
		TAG_NAME = 'name',
		TAG_VALUE = 'value',

		// Video_has_tag table
		TABLE_VIDEO_TAG = 'video_has_tag',
		VIDEO_TAG_VIDEO = 'video_id',
		VIDEO_TAG_TAG = 'tag_id',

		// Video_state table
		TABLE_VIDEO_STATE = 'video_state',
		STATE_ID = 'id',
		STATE_NAME = 'name',

		// User_has_video table
		TABLE_VIDEO_USER = 'user_has_video',
		VIDEO_USER_VIDEO = 'video_id',
		VIDEO_USER_ROLE = 'role_id',

		// Video_relation table
		TABLE_VIDEO_RELATION = 'video_relation',
		VIDEO_RELATION_FROM = 'video_from',
		VIDEO_RELATION_TYPE = 'relation_type_id'
	;

	/**
	 * @var array $parameters
	 * @var Context $database
	 */
	private $parameters, $database;

	public function __construct(array $parameters, Context $database)
	{
		$this->parameters = $parameters;
		$this->database = $database;
	}

	/**
	 * Insert video into database
	 *
	 * @param string $name Name of video
	 * @param int $state State according to `video_state` table
	 * @param int|null $record_begin Timestamp when the recording was started
	 * @param int|null $record_end Timestamp when the recording was stopped
	 * @param string|null $abstract Video abstract
	 *
	 * @return int|null ID of new video or null
	 */
	public function newVideo(string $name="Unnamed", int $state=1, int $record_begin=null, int $record_end=null, string $abstract=null)
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
		return null;
	}

	/**
	 * Get all videos.
	 *
	 * @param string $orderBy The order of videos
	 * @return Nette\Database\Table\Selection All videos.
	 */
	public function getAllVideos(string $orderBy='id DESC')
	{
		return $this->database->table(self::TABLE_VIDEO)->order($orderBy);
	}

	/**
	 * Get latest published videos.
	 *
	 * @param int $limit The limit of videos
	 * @param bool $loggedIn Include videos available after logging in.
	 * @param bool $all Get all videos, otherwise return only public or accessible videos.
	 * @param int $level The level in range of config directive array, null = not applied.
	 * @param array $videosId Videos identifier that will be check for nested tags.
	 *
	 * @return Nette\Database\Table\Selection Published videos in specified tag level (if set).
	 */
	public function getVideos(int $limit=0, bool $loggedIn=false, bool $all=false, int $level=null, array $videosId=[])
	{
		$selection = $this->database->table(self::TABLE_VIDEO);

		// List all or only published videos
		if (!$all) {
			$state = ['done_public'];
			if ($loggedIn) {
				$state[] = 'done_logged_in';
			}
			$stateIDs = $this->database->table(self::TABLE_VIDEO_STATE)
				->where(self::STATE_NAME, $state)
				->fetchPairs(null, self::STATE_ID)
			;
			$selection->where(self::VIDEO_STATE, $stateIDs);
		}

		// If level filtering is set
		if ($level != null) {
			for (; $level < count($this->parameters['required_tags']); $level++) { // If lower level, display all videos
				$id = $this->issetTagValue($this->parameters['required_tags'][$level], null);
				$videosRow = $this->database->table(self::TABLE_VIDEO_TAG)->where(self::VIDEO_TAG_VIDEO, $videosId); // Get rows of suitable videos
				$videosId = $videosRow->where(self::VIDEO_TAG_VIDEO, $id)->fetchPairs(null, self::VIDEO_TAG_VIDEO); // Get list of suitable videos
			}
			$selection->where(self::VIDEO_ID, $videosId);
		}

		$selection->order(self::VIDEO_PUBLISHED.' DESC');

		// If $limit is set
		if ($limit>0) {
			return $selection->limit($limit);
		}

		return $selection;
	}

	/**
	 * Get video row by ID.
	 *
	 * @param int $id ID of video.
	 * @return Nette\Database\Table\ActiveRow|bool ActiveRow or false if there is no such video.
	 */
	public function getVideoById(int $id, bool $all=false, bool $loggedIn=false)
	{
		$video = $this->database->table(self::TABLE_VIDEO)->get($id);

		// Get any or only accessible video
		if (!$all && $video !== false) {
			$state = $video->ref(self::TABLE_VIDEO_STATE, self::VIDEO_STATE)->name;
			if ($loggedIn) {
				if ($state != 'done_public' && $state != 'done_logged_in') {
					return false;
				}
			}
			else {
				if ($state != 'done_public') {
					return false;
				}
			}
		}

		return $video;
	}

	/* TAGS */

	/**
	 * Get tag value of certain video.
	 *
	 * @param int $videoId ID of video.
	 * @param string $tagLevel Name (level) of tag.
	 * @return string|null Value of tag or null when the video has no tag at this level.
	 */
	public function getVideoTagValue(int $videoId, string $tagLevel)
	{
		$row = $this->database->table(self::TABLE_VIDEO_TAG)
			->where(self::VIDEO_TAG_VIDEO, $videoId)
			->select('tag.name AS name, tag.value AS value')
			->where('name', $tagLevel)
			->fetch()
		;

		if ($row) {
			return $row->value;
		}
		else{
			return null;
		}
	}

	/**
	 * Get all values of specified tag.
	 *
	 * @param string $tag The tag
	 * @return array Associative array: 'tag_id'=>'tag_value'.
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
	 * @param array $path The current path (array of tagvalues).
	 * @return array Array containing 'lvl', 'val'
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
			if ($id !== null) {
				$valuesId[] = $id;
				$pathIndex++;
				if (!isset($path[$pathIndex])) { // Done
					break;
				}
			}
		}
		$tagLevel++;

		if (count($valuesId) != count($path)) { // Check for non existing path
			return null;
		}

		// Return nested values containing some video (slow)
		$selection = $this->database->table(self::TABLE_VIDEO_TAG);
		foreach ($valuesId as $id) {
			$videosId = $selection->where(self::VIDEO_TAG_TAG, $id)->fetchPairs(null, self::VIDEO_TAG_VIDEO); // Get list of suitable videos
			$selection = $this->database->table(self::TABLE_VIDEO_TAG)->where(self::VIDEO_TAG_VIDEO, $videosId); // Get rows of suitable videos
		}
		$nestedTagValues['vid'] = $videosId;
		// The lowest level, no nested tags, false while condition
		$nestedTagValues['lvl'] = count($this->parameters['required_tags']);
		$nestedTagValues['val'] = [];
		// While empty, try to go deeper
		while (empty($nestedTagValues['val']) && isset($this->parameters['required_tags'][$tagLevel])) {
			$nestedTagValues['lvl'] = $tagLevel;
			$values = $this->database->table(self::TABLE_VIDEO_TAG)
				->where(self::VIDEO_TAG_VIDEO, $videosId)
				->select('tag.name AS name, tag.value AS value')
				->where('name', $this->parameters['required_tags'][$tagLevel])
				->fetchPairs(null, 'value')
			;
			$nestedTagValues['val'] = array_filter($values);
			$tagLevel++;
		}

		return $nestedTagValues;
	}

	/**
	 * Set duration of video if not already set
	 *
	 * @param int $id ID of video
	 * @param int $duration Duration of video in seconds
	 * @return bool Return true if duration was set. False when nothing was changed.
	 */
	public function setDuration(int $id, int $duration)
	{
		$result = $this->database->table(self::TABLE_VIDEO)->get($id);

		if ($result->duration == null) {
			$result->update([
				self::VIDEO_DURATION => $duration
			]);
			return true;
		}
		return false;
	}

	/**
	 * Return id of row from `video_has_tag` table.
	 *
	 * @param string $tag The tag name
	 * @param string|null $value The value
	 * @return int|null ID of row, or null when combination of name and value does't exit.
	 */
	private function issetTagValue(string $tag, $value)
	{
		$row = $this->database->table(self::TABLE_TAG)
			->where(self::TAG_NAME, $tag)
			->where(self::TAG_VALUE, $value)
			->fetch()
		;

		if ($row !== false) {
			return $row->id;
		}
		return null;
	}

	/**
	 * Find out all people connected to the video.
	 *
	 * @param int $id ID of video.
	 * @return array Rows of people divided into arrays by their role.
	 */
	public function getVideoPeople(int $id)
	{
		$people = [];

		$selection = $this->database->table(self::TABLE_VIDEO_USER)
			->where(self::VIDEO_USER_VIDEO, $id)
			->order(self::VIDEO_USER_ROLE.' ASC')
		;

		$prevRole = -1;
		while ($row = $selection->fetch()) {
			if ($row->role_id != $prevRole) {
				$people[$row->role_id] = [];
				$prevRole = $row->role_id;
			}
			$people[$row->role_id][] = $row;
		}

		return $people;
	}

	/**
	 * Find out all videos marked as related to this video
	 *
	 * @param int $id ID of video.
	 * @return array Rows of video_relation table divided into arrays by relation type.
	 */
	public function getRelatedVideos(int $id)
	{
		$videos = [];

		$selection = $this->database->table(self::TABLE_VIDEO_RELATION)
			->where(self::VIDEO_RELATION_FROM, $id)
			->order(self::VIDEO_RELATION_TYPE.' ASC')
		;

		$prevType = -1;
		while ($row = $selection->fetch()) {
			if ($row->relation_type_id != $prevType) {
				$videos[$row->relation_type_id] = [];
				$prevType = $row->relation_type_id;
			}
			$videos[$row->relation_type_id][] = $row;
		}

		return $videos;
	}

}
