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
		VIDEO_PUBLIC_LINK = 'plane_public_link'
	;

	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $database)
	{
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

	public function getAllVideos(string $orderBy='id DESC')
	{
		return $this->database->table(self::TABLE_VIDEO)->order($orderBy);
	}
}
