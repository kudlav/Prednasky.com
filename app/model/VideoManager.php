<?php
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Database\ResultSet;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Security\User;
use Tracy\Debugger;
use Tracy\ILogger;


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
		VIDEO_RECORD_DATE = 'record_date',
		VIDEO_RECORD_BEGIN = 'record_time_begin',
		VIDEO_RECORD_END = 'record_time_end',
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
		VIDEO_USER_USER = 'user_id',
		VIDEO_USER_EMAIL = 'show_email',

		// Video_relation table
		TABLE_VIDEO_RELATION = 'video_relation',
		VIDEO_RELATION_FROM = 'video_from',
		VIDEO_RELATION_TO = 'video_to',
		VIDEO_RELATION_TYPE = 'relation_type_id',

		// Relation_type table
		TABLE_RELATION_TYPE = 'relation_type',
		RELATION_ID = 'id',
		RELATION_NAME = 'name',

		// Role table
		TABLE_ROLE = 'role',
		ROLE_ID = 'id',
		ROLE_NAME = 'name'
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
	 * @param User $user Owner of video
	 * @param string $name Name of video
	 * @param int $state State according to `video_state` table
	 * @param string|null $date Date when the recording was started
	 * @param string|null $record_begin Time when the recording was started
	 * @param string|null $record_end Time when the recording was stopped
	 * @param string|null $abstract Video abstract
	 * @return int|null ID of new video or null
	 */
	public function newVideo(?User $user=null, string $name="Unnamed", int $state=1, ?string $date=null, ?string $record_begin=null, ?string $record_end=null, string $abstract=null): ?int
	{
		$row = $this->database->table(self::TABLE_VIDEO)->insert([
			self::VIDEO_NAME => $name,
			self::VIDEO_CREATED => date('Y-m-d H:i:s'),
			self::VIDEO_STATE => $state,
			self::VIDEO_RECORD_DATE => $date,
			self::VIDEO_RECORD_BEGIN => $record_begin,
			self::VIDEO_RECORD_END => $record_end,
			self::VIDEO_ABSTRACT => $abstract,
			self::VIDEO_COMPLETE => 0
		]);

		if ($row) {
			Debugger::log("VideoManager: Created video 'id':'$row->id'", \Tracy\ILogger::INFO);

			foreach ($this->parameters['structure_tag'] as $tag) {
				$result = $this->setVideoTagValue((int) $row->id, $tag, (int) $this->getTag($tag, null)->id);
				if (!$result) {
					Debugger::log("VideoManager: Creating video 'id':'$row->id', unable to set default $tag tag", \Tracy\ILogger::ERROR);
				}
			}

			if ($user !== null) {
				$role = $this->database->table(self::TABLE_ROLE)
					->where(self::ROLE_NAME, 'owner')
					->fetch()
				;

				$userHasVideo = $this->database->table(self::TABLE_VIDEO_USER)->insert([
					self::VIDEO_USER_USER => $user->id,
					self::VIDEO_USER_VIDEO => $row->id,
					self::VIDEO_USER_ROLE => $role->id,
					self::VIDEO_USER_EMAIL => false
				]);

				if (!$userHasVideo) {
					Debugger::log("VideoManager: Unable to assign video '$row->id' to user '$user->id'", \Tracy\ILogger::ERROR);
				}
			}

			return (int) $row->id;
		}
		Debugger::log("VideoManager: Unable to create video '$name'", \Tracy\ILogger::ERROR);
		return null;
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

	/**
	 * Remove video by ID.
	 *
	 * @param int $videoID Video to remove.
	 * @return bool True on success, false in case of failure.
	 */
	public function removeVideo(int $videoID): bool
	{
		# Files
		$files = $this->database->table(FileManager::TABLE_VIDEO_FILE)
			->select(FileManager::VIDEO_FILE_FILE .','. FileManager::TABLE_FILE .'.'. FileManager::FILE_TYPE)
			->where(FileManager::VIDEO_FILE_VIDEO, $videoID)
			->where(FileManager::TABLE_FILE .'.'. FileManager::FILE_TYPE .' = "thumbnail" OR '. FileManager::TABLE_FILE .'.'. FileManager::FILE_TYPE .' LIKE "video/%"')
			->fetchPairs(null, FileManager::VIDEO_FILE_FILE)
		;
		$deleted = $this->database->table(FileManager::TABLE_FILE)
			->where(FileManager::FILE_ID, $files)
			->delete()
		;
		if (count($files) === $deleted) {
			Debugger::log("VideoManager: Deleting video $videoID deleted linked files: ". implode(',', $files), ILogger::INFO);
		}
		else {
			Debugger::log("VideoManager: Deleting video $videoID unable to remove all linked files: ". implode(',', $files), ILogger::WARNING);
		}

		# Token
		// TODO remove token physically from drive

		# Video
		$result = $this->database->table(self::TABLE_VIDEO)
			->get($videoID)
			->delete()
		;

		return $result===1 ? true : false;
	}

	/**
	 * Search videos by text in name, abstract or id
	 *
	 * @param string $query Search query.
	 * @param bool $loggedIn True if user is logged in.
	 * @return ResultSet Video rows.
	 */
	public function searchVideos(string $query, bool $loggedIn=false): ResultSet
	{
		$stateQuery = $this->database->table(self::TABLE_VIDEO_STATE);
		if ($loggedIn) {
			$stateQuery->where(self::STATE_NAME, ['public', 'logged_in']);
		}
		else {
			$stateQuery->where(self::STATE_NAME, 'public');
		}
		$stateIds = $stateQuery->fetchPairs(null, self::STATE_ID);

		if ($this->parameters['fulltext_search']) {
			$result = $this->database->query('
				SELECT *
				FROM `video`
				WHERE `state` IN (?)
				AND ((MATCH(`name`,`abstract`) AGAINST (? IN BOOLEAN MODE)) OR `id` = ?)
				ORDER BY 5 * MATCH(`name`) AGAINST (?) + MATCH(`abstract`) AGAINST (?) DESC
			', $stateIds, $query, $query, $query, $query);
		}
		else {
			$result = $this->database->query('
				SELECT *
				FROM `video`
				WHERE `state` IN (?)
				AND `name` like ? OR `abstract` like ? OR `id` = ?
			', $stateIds, '%'.$query.'%', '%'.$query.'%', $query);
		}

		return $result;
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
			$state = $loggedIn ? ['public', 'logged_in'] : ['public'];
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

			$levelsCount = count($this->parameters['structure_tag']);
			if ($level < $levelsCount) { // If lowest level, display all videos
				$tagCount = 0;
				$tagIDs = [];
				$thisLevelVideos = $this->database->table(self::TABLE_VIDEO_TAG);

				for (; $level < $levelsCount; $level++) {
					$tag = $this->getTag($this->parameters['structure_tag'][$level], null)->id;
					$tagIDs[] = $tag;
					$tagCount++;
				}

				$thisLevelVideos->where(self::VIDEO_TAG_TAG, $tagIDs);
				$thisLevelVideos->group(self::VIDEO_TAG_VIDEO);
				$thisLevelVideos->having('COUNT(*) = ?', $tagCount);

				$selection->where(self::VIDEO_ID, $thisLevelVideos->fetchPairs(null, self::VIDEO_TAG_VIDEO));
			}
		}

		$selection->order(self::VIDEO_PUBLISHED.' DESC');

		// If $limit is set
		if ($limit>0) {
			$selection->limit($limit);
		}

		return $selection;
	}

	/**
	 * Get users videos with tags
	 *
	 * @param User $user
	 * @param string|null $state Filter videos with specific state [published,drafts,processing]. NULL = filter not applied.
	 * @return array|null Return rows in `video` table.
	 */
	public function getVideosByUser(User $user, ?string $state = null): ?array
	{
		$select = [];
		$where = [];
		$join = [];

		$select[] = "`video`.*";
		$select[] = "`video_state`.`name` AS `state_name`";
		$join[] = "`video_state` ON `video`.`state`=`video_state`.`id`";

		// Category columns
		foreach ($this->parameters['structure_tag'] as $tag) {
			$select[] = sprintf("`tag_%s`.`value` AS `%s`", $tag, $tag);
			$where[] = sprintf("(`tag_%s`.`name`='%s' OR `tag_%s`.`name` IS NULL)", $tag, $tag, $tag);
			$join[] = sprintf("`video_has_tag` AS vht_%s ON `video`.`id`=vht_%s.`video_id`", $tag, $tag);
			$join[] = sprintf("`tag` AS `tag_%s` ON vht_%s.`tag_id`=`tag_%s`.`id`", $tag, $tag, $tag);
		}

		// Filter owned videos
		if (!$user->isInRole('admin')) {
			$join[] = "`user_has_video` ON `video`.`id`=`user_has_video`.`video_id`";
			$join[] = "`role` ON `user_has_video`.`role_id`=`role`.`id`";
			$where[] = sprintf("`role`.`name`='owner' AND `user_has_video`.`user_id`=%d", $user->id);
		}

		// Filter videos according to visibility
		if ($state !== null) {
			switch ($state) {
				case 'published':
					$where[] = "`video`.`complete`=1";
					$where[] = "(`video`.`public_link` IS NOT NULL OR `video_state`.`name` IN ('public', 'logged_in'))";
					break;

				case 'drafts':
					$where[] = "`video`.`complete`=1";
					$where[] = "`video`.`public_link` IS NULL";
					$where[] = "`video_state`.`name`='private'";
					break;

				case 'processing':
					$where[] = "`video`.`complete`=0";
					break;

				default:
					Debugger::log("VideoManager: unknown option '$state' of getVideosByUser", ILogger::ERROR);
			}
		}

		$query = "SELECT " . implode(",", $select)
			. " FROM `video`"
			. " LEFT JOIN " . implode(" LEFT JOIN ", $join)
			. " WHERE " . implode(" AND ", $where)
		;

		return $this->database->query($query)->fetchAssoc('id');
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
	 * Create relation between video and user.
	 *
	 * @param int $userId
	 * @param int $videoId
	 * @param int $roleId
	 * @param bool $showEmail
	 * @return bool Success/failure.
	 */
	public function addVideoPeople(int $userId, int $videoId, int $roleId, bool $showEmail): bool {
		try {
			$result = $this->database->table(self::TABLE_VIDEO_USER)->insert([
				self::VIDEO_USER_USER => $userId,
				self::VIDEO_USER_VIDEO => $videoId,
				self::VIDEO_USER_ROLE => $roleId,
				self::VIDEO_USER_EMAIL => $showEmail,
			]);
		}
		catch (Nette\Database\UniqueConstraintViolationException $e) {
			return false;
		}

		return ($result !== false);
	}

	/**
	 * Remove relation between video and user.
	 *
	 * @param int $userId
	 * @param int $videoId
	 * @param int $roleId
	 * @return bool Success/failure.
	 */
	public function removeVideoPeople(int $userId, int $videoId, int $roleId) {
		$result = $this->database->table(self::TABLE_VIDEO_USER)
			->where(self::VIDEO_USER_USER, $userId)
			->where(self::VIDEO_USER_VIDEO, $videoId)
			->where(self::VIDEO_USER_ROLE, $roleId)
			->delete()
		;

		return ($result === 1);
	}

	/**
	 * Get all user roles in connection with video
	 *
	 * @return Selection rows of roles
	 */
	public function getRoles(): Selection
	{
		return $this->database->table(self::TABLE_ROLE);
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
	 * Get all video relation types
	 *
	 * @return Selection of relation types.
	 */
	public function getRelationTypes(): Selection
	{
		return $this->database->table(self::TABLE_RELATION_TYPE);
	}

	/**
	 * Add one-way relation between videoFrom and videoTo.
	 *
	 * @param $videoFrom
	 * @param $videoTo
	 * @param $relation
	 * @return bool Success/failure.
	 */
	public function addVideoRelation($videoFrom, $videoTo, $relation): bool
	{
		try {
			$result = $this->database->table(self::TABLE_VIDEO_RELATION)->insert([
				self::VIDEO_RELATION_FROM => $videoFrom,
				self::VIDEO_RELATION_TO => $videoTo,
				self::VIDEO_RELATION_TYPE => $relation,
			]);
		}
		catch (Nette\Database\UniqueConstraintViolationException $e) {
			return false;
		}

		return ($result !== false);
	}

	/**
	 * Remove one-way relation between videoFrom and videoTo.
	 *
	 * @param $videoFrom
	 * @param $videoTo
	 * @param $relation
	 * @return bool Success/failure.
	 */
	public function removeVideoRelation($videoFrom, $videoTo, $relation): bool
	{
		$result = $this->database->table(self::TABLE_VIDEO_RELATION)
			->where(self::VIDEO_RELATION_FROM, $videoFrom)
			->where(self::VIDEO_RELATION_TO, $videoTo)
			->where(self::VIDEO_RELATION_TYPE, $relation)
			->delete()
		;

		return ($result === 1);
	}

	/**
	 * Get available states.
	 *
	 * @return array Array containing id => name.
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
	 * @param int|bool $tagId Row ID of combination tag-value.
	 * @return bool True when tag was added/updated.
	 */
	public function setVideoTagValue(int $videoId, string $tag, int $tagId)
	{
		$currentVal = $this->getVideoTagValue((int) $videoId, $tag);

		// Skip if value is already set
		if (($currentVal === null) || $currentVal->id !== $tagId) {

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

			// Insert new tag
			$result = $this->database->table(self::TABLE_VIDEO_TAG)->insert([
				self::VIDEO_TAG_VIDEO => $videoId,
				self::VIDEO_TAG_TAG => $tagId,
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
	public function getTagValues(string $tag): array
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

}
