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
    return pathinfo($file, PATHINFO_EXTENSION);
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

  private function isValidFile($file)
  {
    if (!file_exists($this->path.$file)) {
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

  public function getPath()
  {
    return $this->path;
  }

  public function write($content, $file)
  {
    echo "Writing $content to $file";
    return true;
  }
}
