<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;


class FileManager
{
	use Nette\SmartObject;

	const
		// Table file
		TABLE_FILE = 'file',
		FILE_TYPE = 'type',
		FILE_PATH = 'path',

		// Table video_has_file
		TABLE_VIDEO_FILE = 'video_has_file',
		VIDEO_FILE_VIDEO = 'video_id',
		VIDEO_FILE_FILE = 'file_id'
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
	 * Get array of video files.
	 *
	 * @param int $videoId Video ID.
	 * @return array Associative array [videoType => videoPath].
	 */
	public function getVideoFiles(int $videoId)
	{
		$type = ['video/mp4', 'video/webm', 'video/ogg'];
		return $this->getVideoFile($videoId, $type)
			->select('file.type AS type, file.path AS path')
			->fetchPairs(self::FILE_TYPE, self::FILE_PATH);
	}

	/**
	 * Get row of video thumbnail.
	 *
	 * @param int $videoId Video ID.
	 * @return FALSE|Nette\Database\Table\ActiveRow ActiveRow if thumbnail exists, otherwise return FALSE.
	 */
	public function getVideoThumbnail(int $videoId)
	{
		$type = 'thumbnail';
		return $this->getVideoFile($videoId, $type)->fetch();
	}

	/**
	 * Get rows with video attachments.
	 *
	 * @param int $videoId Video ID.
	 * @return Nette\Database\Table\Selection Selection of video attachment rows.
	 */
	public function getVideoAttachments(int $videoId)
	{
		$type = 'attachment';
		return $this->getVideoFile($videoId, $type);
	}

	/**
	 * Get rows with files of specified type connected with video.
	 *
	 * @param int $videoId Video ID.
	 * @param string $type Type of file.
	 * @return Nette\Database\Table\Selection Selection of certain type files connected to video.
	 */
	private function getVideoFile($videoId, $type)
	{
		return $this->database->table(self::TABLE_VIDEO_FILE)
			->where(self::VIDEO_FILE_VIDEO, $videoId)
			->where(self::TABLE_FILE.'.'.self::FILE_TYPE, $type)
		;
	}

}
