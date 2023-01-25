<?php
namespace App\Utils;

use ZipArchive;

class ZipUtils {
    protected $tmpZipFileLocation;
    protected $tmpZipFile;
    protected $tmpZipPassword = null;

    public function __construct()
    {
        $this->create();
    }

    public function create(): void{
        $this->tmpZipFileLocation = sys_get_temp_dir(). '/zip_' . uniqid() .rand(1, 9999);
        $this->tmpZipFile = new ZipArchive();
        $this->tmpZipFile->open($this->tmpZipFileLocation, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
        $this->clearPassword();
    }

    public function close(): void{
        $this->tmpZipFile->close();
    }

    public function setPassword(string $password): void{
        $this->tmpZipFile->setPassword($password);
        $this->tmpZipPassword = $password;
    }

    public function clearPassword(){
        $this->tmpZipPassword = null;
    }

    public function addFile($fileLocation, $newName = null): void{
        if($newName === null) $newName = basename($fileLocation);

        $this->tmpZipFile->addFile($fileLocation, $newName);

        if($this->tmpZipPassword != null)
            $this->tmpZipFile->setEncryptionName($newName, ZipArchive::EM_AES_256);
    }

    public function addDir(string $currPath,?string $basePath = null): void{
        $currPath = rtrim($currPath, '/');
        if($basePath === null || !str_contains($currPath, $basePath))
            $basePath = $currPath;

        $handle = opendir($currPath);

        while (false !== $f = readdir($handle)) {
            if ($f == '.' || $f == '..') continue;

            $fileLocation = "{$currPath}/{$f}";

            $zipPath = ltrim( str_replace($basePath, '', $fileLocation), '/');
            if (is_file($fileLocation)) {
                $this->addFile($fileLocation, $zipPath);
            } elseif (is_dir($fileLocation)) {
                $this->tmpZipFile->addEmptyDir($zipPath);
                $this->addDir($fileLocation, $basePath);
            }
        }

        closedir($handle);
    }

    public function save(string $saveLocation, ?string $password = null): string{
        if( !is_dir(dirname($saveLocation)) ) return false;

        $this->close();
        copy($this->tmpZipFileLocation, $saveLocation);
        $this->create();

        return true;
    }

    public function getContent(): string{
        $this->close();
        $content = file_get_contents($this->tmpZipFileLocation);
        $this->create();

        return $content;
    }

    public function extractTo(string $location, string $extractPath, ?string $password = null): void{
        $zip = new ZipArchive();
        $res = $zip->open( $location );
        if( $password !== null) $zip->setPassword($password);
        $zip->extractTo($extractPath);
        $zip->close();
    }

    public function __destruct()
    {
        $this->close();
    }
}
