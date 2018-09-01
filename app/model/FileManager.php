<?php
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Http\FileUpload;
use \Tracy\Debugger;


class FileManager
{
	use Nette\SmartObject;

	const
		// Table file
		TABLE_FILE = 'file',
		FILE_ID = 'id',
		FILE_TYPE = 'type',
		FILE_PATH = 'path',
		FILE_NAME = 'name',
		FILE_DOWNLOADS = 'downloads',
		FILE_UPLOADED = 'uploaded',

		// Table video_has_file
		TABLE_VIDEO_FILE = 'video_has_file',
		VIDEO_FILE_VIDEO = 'video_id',
		VIDEO_FILE_FILE = 'file_id',
		VIDEO_FILE_SHOW = 'show'
	;


	/**
	 * @var string $tmpDir
	 * @var array $parameters
	 * @var Context $database
	 */
	private $tmpDir, $parameters, $database;

	public function __construct(string $tmpDir, array $parameters, Context $database)
	{
		$this->tmpDir = $tmpDir;
		$this->parameters = $parameters;
		$this->database = $database;
	}

	/**
	 * Create new file in table `file`.
	 *
	 * @param string $type Type of file.
	 * @param string $path Path of file after 'path_data_export' directive or 'path_attachment' for attachments.
	 * @param string|null $name Optional name of file. Important for attachments.
	 * @return int|null ID of new file or null
	 */
	public function newFile(string $type, string $path, string $name=null): ?int
	{
		$row = $this->database->table(self::TABLE_FILE)->insert([
			self::FILE_TYPE => $type,
			self::FILE_PATH => $path,
			self::FILE_NAME => $name,
			self::FILE_DOWNLOADS => 0,
			self::FILE_UPLOADED => date('Y-m-d H:i:s')
		]);

		if ($row) {
			\Tracy\Debugger::log("FileManager: Created file 'id':'".$row->id."'", \Tracy\ILogger::INFO);
			return (int) $row->id;
		}
		\Tracy\Debugger::log("FileManager: Unable to create file '".$path."'", \Tracy\ILogger::ERROR);
		return null;
	}

	/**
	 * Link file and video by id. Create record in table `video_has_file`
	 *
	 * @param int $videoId ID of video.
	 * @param int $fileId ID if file.
	 * @param bool $show If attachment, show in file list under video.
	 * @return bool True on success, otherwise false.
	 */
	private function linkVideoFile(int $videoId, int $fileId, bool $show=false): bool
	{
		$row = $this->database->table(self::TABLE_VIDEO_FILE)->insert([
			self::VIDEO_FILE_VIDEO => $videoId,
			self::VIDEO_FILE_FILE => $fileId,
			self::VIDEO_FILE_SHOW => $show,
		]);

		if ($row) {
			\Tracy\Debugger::log("FileManager: File #". $fileId ." linked to video #". $videoId, \Tracy\ILogger::INFO);
			return true;
		}
		\Tracy\Debugger::log("FileManager: Unable to link file #". $fileId ." to video #". $videoId, \Tracy\ILogger::ERROR);
		return false;
	}

	/**
	 * Get array of video files.
	 *
	 * @param int $videoId Video ID.
	 * @return array Associative array [videoType => videoPath].
	 */
	public function getVideoFiles(int $videoId): array
	{
		$type = 'video/%'; // e.g. 'video/mp4', 'video/webm', 'video/ogg'
		return $this->getVideoFileByType($videoId, $type)
			->select('file.type AS type, file.path AS path')
			->fetchPairs(self::FILE_TYPE, self::FILE_PATH)
		;
	}

	/**
	 * Get row of video thumbnail.
	 *
	 * @param int $videoId Video ID.
	 * @return ActiveRow|null ActiveRow if thumbnail exists, otherwise return null.
	 */
	public function getVideoThumbnail(int $videoId): ?ActiveRow
	{
		$type = 'thumbnail';
		$result = $this->getVideoFileByType($videoId, $type)->fetch();
		return $result!==false ? $result->ref(FileManager::TABLE_FILE) : null;
	}

	/**
	 * Get rows with video attachments.
	 *
	 * @param int $videoId Video ID.
	 * @return Selection Selection of video attachment rows.
	 */
	public function getVideoAttachments(int $videoId): Selection
	{
		$type = 'attachment/%';
		return $this->getVideoFileByType($videoId, $type);
	}

	/**
	 * Get rows with files of specified type connected with video.
	 *
	 * @param int $videoId Video ID.
	 * @param string $type Type of file.
	 * @return Selection Selection of certain type files connected to video.
	 */
	private function getVideoFileByType(int $videoId, string $type): Selection
	{
		return $this->database->table(self::TABLE_VIDEO_FILE)
			->where(self::VIDEO_FILE_VIDEO, $videoId)
			->where(self::TABLE_FILE.'.'.self::FILE_TYPE.' LIKE ?', $type)
		;
	}

	/**
	 * Assign files from WORKER/DATA-EXPORT files.list to video (`video_has_file` table).
	 *
	 * @param ActiveRow $token Row containing successfully finished token.
	 * @return bool True on success, otherwise false.
	 */
	public function filesFromToken(ActiveRow $token): bool
	{
		$pathFilesList = $this->parameters['paths']['path_data_export']
						.'/'. $token->created->format('Y/m/d')
						.'/'. $token->public_hash
						.'/'. $token->private_hash
						.'/files.list'
		;

		$filesList = is_readable($pathFilesList) ? fopen($pathFilesList, 'r') : false;

		if ($filesList === false) {
			Debugger::log("FileManager: import files from token # '".$token->id.", no file 'files.list'.", \Tracy\ILogger::INFO);
			return false;
		}

		$newVideoFile = false;

		while (($line = fgets($filesList)) != null) {
			$line = trim($line);
			$expFilePath = explode('DATA-EXPORT', $line, 2);

			if (count($expFilePath) != 2) {
				Debugger::log("FileManager: Unexpected file path '".$line."'", \Tracy\ILogger::ERROR);
				return false;
			}

			$expFileName = explode('/', $expFilePath[1]);
			switch (end($expFileName)) {
				case 'thumbnail.jpg':
					$fileType = 'thumbnail';
					break;
				default:
					$fileType = mime_content_type($line);
					if (strpos($fileType, 'video/') !== false) {
						$newVideoFile = true;
					}
			}

			$fileId = $this->newFile($fileType, $expFilePath[1]);
			if ($fileId == null) {
				return false;
			}
			$this->linkVideoFile((int) $token->video, $fileId);
		}

		// Video has now at least one video file, mark as completed
		if ($newVideoFile) {
			$this->database->table(VideoManager::TABLE_VIDEO)
				->get($token->video)
				->update([
					VideoManager::VIDEO_COMPLETE => 1
				])
			;
		}

		return true;
	}

	/**
	 * Save one piece of file into temp directory.
	 *
	 * @param FileUpload $file Sent file.
	 * @param string $id ID of upload generated by dropzone.
	 * @param int $part Number (index) of the part.
	 * @return bool True on success, otherwise false.
	 */
	public function uploadFilePart(FileUpload $file, string $id, int $part): bool
	{
		if ($file->getError()) {
			return false;
		}

		$file->move($this->tmpDir .'/'. $id .'/'. $part);

		return true;
	}

	/**
	 * Merge uploaded pieces into original file.
	 *
	 * @param string $id ID of upload generated by dropzone.
	 * @param string $target Original filename.
	 * @return bool True on success, otherwise false.
	 */
	public function uploadFileEnd(string $id, string $target): bool
	{
		$parts = count(glob($this->tmpDir .'/'. $id .'/*'));
		for ($index = 0; $index < $parts; $index++) {
			$fileName = $this->tmpDir .'/'. $id .'/'. $index;

			$filePart = file_get_contents($fileName);
			if ($filePart === false) {
				return false;
			}

			$count = file_put_contents($this->tmpDir .'/'. $id .'/' . $target, $filePart, FILE_APPEND);
			if ($count === false) {
				return false;
			}

			unlink($fileName);
		}

		return true;
	}

	/**
	 * Get path to directory used for uploading files.
	 *
	 * @return string Path to temp/files.
	 */
	public function getTempDir(): string
	{
		return $this->tmpDir;
	}

}
