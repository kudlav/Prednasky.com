<?php
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;


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
		VIDEO_LINK = 'public_link',
		VIDEO_COMPLETE = 'complete',

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
	public function newVideo(string $name="Unnamed", int $state=1, int $record_begin=null, int $record_end=null, string $abstract=null): ?int
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
			return (int) $row->id;
		}
		\Tracy\Debugger::log("VideoManager: Unable to create video '".$name."'", \Tracy\ILogger::ERROR);
		return null;
	}

	/**
	 * Get all videos.
	 *
	 * @param string $orderBy The order of videos
	 * @return Selection All videos.
	 */
	public function getAllVideos(string $orderBy='id DESC'): Selection
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
	 * @return Selection Published videos in specified tag level (if set).
	 */
	public function getVideos(int $limit=0, bool $loggedIn=false, bool $all=false, int $level=null, array $videosId=[]): Selection
	{
		$selection = $this->database->table(self::TABLE_VIDEO);

		// List all or only published videos
		if (!$all) {
			$state = ['public'];
			if ($loggedIn) {
				$state[] = 'logged_in';
			}
			$stateIDs = $this->database->table(self::TABLE_VIDEO_STATE)
				->where(self::STATE_NAME, $state)
				->fetchPairs(null, self::STATE_ID)
			;
			$selection->where(self::VIDEO_STATE, $stateIDs);
			$selection->where(self::VIDEO_COMPLETE, 1);
		}

		// If level filtering is set
		if ($level != null) {
			$selection->where(self::VIDEO_ID, $videosId);

			$tagCount = 0;
			$tagIDs = [];
			$thisLevelVideos = $this->database->table(self::TABLE_VIDEO_TAG);

			for (; $level < count($this->parameters['structure_tag']); $level++) { // If lower level, display all videos
				$tag = $this->getTag($this->parameters['structure_tag'][$level], null)->id;
				$tagIDs[] = $tag;
				$tagCount++;
			}

			$thisLevelVideos->where(self::VIDEO_TAG_TAG, $tagIDs);
			$thisLevelVideos->group(self::VIDEO_TAG_VIDEO);
			$thisLevelVideos->having('COUNT(*) = ?', $tagCount);

			$selection->where(self::VIDEO_ID, $thisLevelVideos->fetchPairs(null, self::VIDEO_TAG_VIDEO));
		}

		$selection->order(self::VIDEO_PUBLISHED.' DESC');

		// If $limit is set
		if ($limit>0) {
			return $selection->limit($limit);
		}

		return $selection;
	}

	/**
	 * Get videos with tags. Any tag with NULL value is skipped.
	 *
	 * @param array $tagIds Array containing tag IDs.
	 * @return Selection Return rows in `video` table.
	 */
	public function getVideosByTag(array $tagIds): Selection
	{
		$tagRows = $this->database->table(self::TABLE_TAG)
			->where(self::TAG_ID, $tagIds);

		$filteredTagIds = [];
		foreach ($tagRows as $tag) {
			if ($tag->value !== null) {
				$filteredTagIds[] = $tag->id;
			}
		}

		$videoIds = $this->database->table(self::TABLE_VIDEO_TAG)
			->where(self::VIDEO_TAG_TAG, $filteredTagIds)
			->group(self::VIDEO_TAG_VIDEO)
			->having('COUNT(*) = ?', count($filteredTagIds))
			->fetchPairs(null, self::VIDEO_TAG_VIDEO)
		;

		$selection = $this->database->table(self::TABLE_VIDEO)
			->where(self::VIDEO_ID, $videoIds);

		return $selection;
	}

	/**
	 * Get video row by ID.
	 *
	 * @param int $id ID of video.
	 * @param bool $all Get private video as well.
	 * @param bool $loggedIn Is the user logged in?
	 * @param string $passphrase Secret token for accessing video.
	 * @return null|ActiveRow ActiveRow or null if there is no such video.
	 */
	public function getVideoById(int $id, bool $all=false, bool $loggedIn=false, string $passphrase=null): ?ActiveRow
	{
		$video = $this->database->table(self::TABLE_VIDEO)->get($id);

		if ($video === false) {
			return null;
		}

		// Get any or only accessible video
		if (!$all) {

			// Check if video is complete
			if (!$video->complete) {
				return null;
			}

			$state = $video->ref(self::TABLE_VIDEO_STATE, self::VIDEO_STATE)->name;

			switch ($state) {
				case 'public':
					return $video;

				case 'logged_in':
					if ($loggedIn) {
						return $video;
					}

				case 'private':
					if ($passphrase === null || $passphrase != $video->public_link) {
						return null;
					}
					break;

				default:
					return null;
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
	 * @return ActiveRow|null Value of tag or null when the video has no tag at this level.
	 */
	public function getVideoTagValue(int $videoId, string $tagLevel): ?ActiveRow
	{
		$row = $this->database->table(self::TABLE_VIDEO_TAG)
			->where(self::VIDEO_TAG_VIDEO, $videoId)
			->select('tag.id AS id, tag.name AS name, tag.value AS value')
			->where('name', $tagLevel)
			->fetch()
		;

		return $row!==false ? $row : null;
	}

	/**
	 * Set tag - value to the video.
	 *
	 * @param int $videoId Id of video.
	 * @param string $tag Tag name.
	 * @param string $value Tag value.
	 * @return bool True when tag was added/updated.
	 */
	public function setVideoTagValue(int $videoId, string $tag, ?string $value)
	{
		$currentVal = $this->getVideoTagValue((int) $videoId, $tag);

		// Skip if value is already set
		if ($currentVal === null || $currentVal->value !== $value) {

			// Tag doesn't exist
			$newTag = $this->getTag($tag, $value);
			if ($newTag === null) {
				return false;
			}

			// Remove existing tag
			if ($currentVal !== null) {
				$currentTag = $this->getTag($tag, $currentVal->value);
				if ($currentTag !== null) {
					$this->database->table(self::TABLE_VIDEO_TAG)
						->where(self::VIDEO_TAG_VIDEO, $videoId)
						->where(self::VIDEO_TAG_TAG, $currentTag->id)
						->delete()
					;
				}
			}

			$result = $this->database->table(self::TABLE_VIDEO_TAG)->insert([
				self::VIDEO_TAG_VIDEO => $videoId,
				self::VIDEO_TAG_TAG => $newTag->id,
			]);

			// Failed to update
			if (!$result) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get all values of specified tag.
	 *
	 * @param string $tag The tag
	 * @return array Associative array: 'tag_id'=>'tag_value'.
	 */
	private function getTagValues(string $tag): array
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
	 * @return null|array Array containing 'lvl', 'val'
	 */
	public function getNestedTagValues(array $path): ?array
	{
		// RETURN: Top level - returning all values of root tag (fast)
		if (empty($path)) {
			return [
				'lvl' => 0,
				'val' => $this->getTagValues($this->parameters['structure_tag'][0]),
			];
		}

		$valuesId = [];
		$tagLevel = 0;
		$pathIndex = 0;

		// Get get list of required tags. Videos have to contain all of them.
		for ($tagLevel; $tagLevel<count($this->parameters['structure_tag']); $tagLevel++) {
			$tag = $this->getTag($this->parameters['structure_tag'][$tagLevel], $path[$pathIndex]);
			if ($tag !== null) {
				$valuesId[] = $tag->id;
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
		$nestedTagValues['lvl'] = count($this->parameters['structure_tag']);
		$nestedTagValues['val'] = [];
		// While empty, try to go deeper
		while (empty($nestedTagValues['val']) && isset($this->parameters['structure_tag'][$tagLevel])) {
			$nestedTagValues['lvl'] = $tagLevel;
			$values = $this->database->table(self::TABLE_VIDEO_TAG)
				->where(self::VIDEO_TAG_VIDEO, $videosId)
				->select('tag.name AS name, tag.value AS value')
				->where('name', $this->parameters['structure_tag'][$tagLevel])
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
	public function setDuration(int $id, int $duration): bool
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
	 * Get row from TAG table using tag name and value.
	 *
	 * @param string $tagName
	 * @param string|null $tagValue
	 * @return ActiveRow|null
	 */
	public function getTag(string $tagName, ?string $tagValue): ?ActiveRow
	{
		$result = $this->database->table(self::TABLE_TAG)
			->where(self::TAG_NAME, $tagName)
			->where(self::TAG_VALUE, $tagValue)
			->fetch();

		return $result!==false ? $result : null;
	}

	/**
	 * Find out all people connected to the video.
	 *
	 * @param int $id ID of video.
	 * @return array Rows of people divided into arrays by their role.
	 */
	public function getVideoPeople(int $id): array
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
	public function getRelatedVideos(int $id): array
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

	/**
	 * Get available states.
	 *
	 * @return array Array containing id => name;
	 */
	public function getStates(): array
	{
		return $this->database->table(self::TABLE_VIDEO_STATE)
			->fetchPairs(self::STATE_ID, self::STATE_NAME)
		;
	}

	/**
	 * Get unique token for accessing video.
	 *
	 * @param int $id Video ID.
	 * @return null|string Token containing 32 chars, or null when no link is created.
	 */
	public function getShareLink(int $id): ?string
	{
		$link = $this->database->table(self::TABLE_VIDEO)
			->get($id)
			->public_link
		;

		return $link!==null ? (string) $link : null;
	}

	/**
	 * Create or clear unique token for accessing video.
	 *
	 * @param int $id Video ID.
	 * @param bool $clear Set true to remove unique token.
	 * @return null|string Token containing 32 chars.
	 * @throws \Exception If random_bytes is unable to create secure token.
	 */
	public function setShareLink(int $id, bool $clear=false): ?string
	{
		if ($clear) {
			$token = null;
		}
		else {
			$token = bin2hex(random_bytes(16));
		}

		$this->database->table(self::TABLE_VIDEO)
			->where(self::VIDEO_ID, $id)
			->update([
				self::VIDEO_LINK => $token
			])
		;

		return $token;
	}

	/**
	 * Update multiple values of existing video.
	 *
	 * @param int $id Video to update.
	 * @param array $values New values to insert.
	 * @return bool True on success.
	 */
	public function updateVideo(int $id, array $values): bool
	{
		return $this->database->table(self::TABLE_VIDEO)
			->get($id)
			->update($values)
		;
	}

}
