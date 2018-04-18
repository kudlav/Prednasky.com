<?php

namespace App\Model;

use Nette;


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


	/** @var Nette\Database\Context */
	private $parameters, $database;

	public function __construct($parameters, Nette\Database\Context $database)
	{
		$this->parameters = $parameters;
		$this->database = $database;
	}

	/**
	 *
	 *
	 * @param $videoId
	 * @return array
	 */
	public function getVideoFiles($videoId)
	{
		$type = ['video/mp4', 'video/webm', 'video/ogg'];
		return $this->getVideoFile($videoId, $type)->fetchPairs(self::FILE_TYPE, self::FILE_PATH);
	}

	/**
	 *
	 *
	 * @param $videoId
	 * @return false|Nette\Database\Table\ActiveRow
	 */
	public function getVideoThumbnail($videoId)
	{
		$type = 'thumbnail';
		return $this->getVideoFile($videoId, $type)->fetch();
	}

	/**
	 *
	 *
	 * @param $videoId
	 * @param $type
	 * @return Nette\Database\Table\Selection
	 */
	private function getVideoFile($videoId, $type)
	{
		return $this->database->table(self::TABLE_VIDEO_FILE)
			->where(self::VIDEO_FILE_VIDEO, $videoId)
			->where(self::TABLE_FILE.'.'.self::FILE_TYPE, $type)
		;
	}

}
