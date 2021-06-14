<?php

namespace App\Services;

use ImageKit\ImageKit;

class FileStorageService
{
    private $publicKey;
    private $privateKey;
    private $endpoint;

    public function __construct()
    {
        $this->publicKey = config("constants.MEDIA_STORAGE.PUBLIC_KEY");
        $this->privateKey = config("constants.MEDIA_STORAGE.PRIVATE_KEY");
        $this->endpoint = config("constants.MEDIA_STORAGE.ENDPOINT");
    }

    public static function getFullFilePathForProfileAvatar($fileName)
    {
        if (is_null($fileName)) {
            return config("constants.MEDIA_STORAGE.ENDPOINT") . config("constants.FILE_PATHS.USER_PROFILE_PHOTO") . config("constants.DEFAULT_FILES.USER_PROFILE_PHOTO");
        }
        return config("constants.MEDIA_STORAGE.ENDPOINT") . config("constants.FILE_PATHS.USER_PROFILE_PHOTO") . self::extractFileName($fileName);
    }

    private static function extractFileName($fileNameToExtract)
    {
        $fileName = explode("-", $fileNameToExtract);
        $extension = explode(".", $fileNameToExtract);
        return $fileName[0] . "." . $extension[1];
    }

    public function storefile($file, $fileName, $pathToFile)
    {
        $imageKit = new ImageKit($this->publicKey, $this->privateKey, $this->endpoint);
        return $imageKit->upload(array(
            "file" => $this->convertFileToBase64($file),
            "fileName" => $fileName,
            "useUniqueFileName" => false,
            "folder" => $pathToFile,
        ));
    }

    private function convertFileToBase64($file)
    {
        return base64_encode(file_get_contents($file));
    }

    public function distroyFile($fileName)
    {
        $fileId = self::extractFileId($fileName);
        $imageKit = new ImageKit($this->publicKey, $this->privateKey, $this->endpoint);
        return $imageKit->deleteFile($fileId);
    }

    private static function extractFileId($fileNameToExtract)
    {
        $extension = explode(".", $fileNameToExtract);
        $fieldId = explode("-", $extension[0]);
        return $fieldId[1];
    }

    public function clearCache($fileName, $pathToFile)
    {
        $fileUrl = config("constants.MEDIA_STORAGE.ENDPOINT") . $pathToFile . self::extractFileName($fileName);
        $imageKit = new ImageKit($this->publicKey, $this->privateKey, $this->endpoint);
        return $imageKit->purgeFileCacheApi($fileUrl);
    }
}
