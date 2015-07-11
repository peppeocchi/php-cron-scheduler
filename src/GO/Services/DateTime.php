<?php namespace GO\Services;

class DateTime
{
  private static $dt = null;

  private function __construct()
  {
    self::$dt = new \DateTime('now');

    return self::$dt;
  }

  public static function get()
  {
    if (self::$dt === null) {
      return new self;
    }

    return self::$dt;
  }

  public static function setTimezone($timezone)
  {
    self::$dt->setTimezone(new \DateTimeZone($timezone));
  }

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
