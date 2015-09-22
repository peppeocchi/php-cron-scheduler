<?php namespace GO\Services;

class Filesystem
{
  /**
   * Path
   *
   * @var string
   */
  private $path;

  /**
   * File/s
   *
   * @var mixed - string or array
   */
  private $file;

  /**
   * File extension
   *
   * @var string
   */
  private $extension;

  /**
   * Create new instance and analyse path
   *
   * @param string $path
   * @return void
   */
  public function __construct($path)
  {
    $this->file = basename($path);
    $this->path = str_replace($this->file, '', $path);

    $this->extension = $this->getExtension($this->file);

    if (strpos($this->file, '*') !== false) {
      $this->file = $this->getFiles($this->path);
    }
  }

  /**
   * Get extension from file
   *
   * @param string $file
   * @return string
   */
  private function getExtension($file)
  {
    return pathinfo($file, PATHINFO_EXTENSION);
  }

  /**
   * Get files in path
   *
   * @param string $path
   * @return array
   */
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

  /**
   * Check if is a valid file
   *
   * @param string $file
   * @return bool
   */
  private function isValidFile($file)
  {
    if (!file_exists($this->path.$file)) {
      return false;
    }

    // Check permissions?

    if ($this->getExtension($file) === $this->extension) {
      return false;
    }

    return true;
  }

  /**
   * Get file/s
   *
   * @return mixed
   */
  public function getCommand()
  {
    return $this->file;
  }

  /**
   * Get path
   *
   * @return string
   */
  public function getPath()
  {
    return $this->path;
  }

  /**
   * Write to file
   *
   * @param string $content
   * @param string $file
   * @param string $mode
   * @return bool
   */
  public static function write($content, $file, $mode = 'w')
  {
    $handle = fopen($file, $mode);
    fwrite($handle, $content . "\n");
    fclose($handle);

    return true;
  }
}
