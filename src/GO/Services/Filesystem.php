<?php namespace GO\Services;

class Filesystem
{
  private $path;
  private $file;
  private $extension;

  public function __construct($path)
  {
    $this->file = basename($path);
    $this->path = str_replace($this->file, '', $path);

    $this->extension = $this->getExtension($this->file);

    if (strpos($this->file, '*') !== false) {
      $this->file = $this->getFiles($this->path);
    }
  }

  private function getExtension($file)
  {
    $extension = explode('.', $file);

    return $extension[count($extension) - 1];
  }

  private function getFiles($path)
  {
    if (!is_dir($path)) {
      throw new \Exception('Invalid path');
    }

    $files = scandir($path);

    $return = [];

    foreach ($files as $file) {
      if ($file == '.' || $file == '..') {
        continue;
      }

      if (is_dir($file)) {
        // Recursive?
        continue;
      }

      if ($this->isValidFile($file)) {
        array_push($return, $file);
      }
    }

    return $return;
  }

  private function isValidFile($file, $sub = '')
  {
    if (!file_exists($this->path.$sub.$file)) {
      return false;
    }

    // Check permissions

    // Change for regex
    if (strpos($file, $this->extension) === false) {
      return false;
    }

    return true;
  }

  public function getCommand()
  {
    return $this->file;
  }
}
