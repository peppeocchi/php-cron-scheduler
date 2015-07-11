<?php namespace GO\Services;

class DateTime
{
  /**
   * Singleton DateTime instance
   *
   * @var \DateTime
   */
  private static $dt = null;

  /**
   * Private constructor
   *
   * @return \DateTime
   */
  private function __construct()
  {
    self::$dt = new \DateTime('now');

    return self::$dt;
  }

  /**
   * Get singleton
   *
   * @return \DateTime
   */
  public static function get()
  {
    if (self::$dt === null) {
      return new self;
    }

    return self::$dt;
  }

  /**
   * Set timezone
   *
   * @param string $timezone
   * @return void
   */
  public static function setTimezone($timezone)
  {
    self::$dt->setTimezone(new \DateTimeZone($timezone));
  }

  /**
   * Get time now in array format
   *
   * @return array
   */
  public static function now()
  {
    $dt = self::get();

    return [
      'second' => $dt->format('s'),
      'minute' => $dt->format('i'),
      'hour'   => $dt->format('H'),
      'day'    => $dt->format('d'),
      'month'  => $dt->format('m'),
      'year'   => $dt->format('Y'),
    ];
  }
}
