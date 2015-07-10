<?php namespace GO\Services;

use GO\Services\DateTime;

class TimeParser
{
  private $expression;

  private $minute;
  private $hour;
  private $day;
  private $month;
  private $dayOfWeek;

  public function __construct($expression)
  {
    $this->expression = $this->analyse(trim($expression));
  }

  private function analyse($expression)
  {
    $cron = explode(' ', $expression);
    $this->minute = $this->parse($cron[0]);
    $this->hour = $this->parse($cron[1]);
    $this->day = $this->parse($cron[2]);
    $this->month = $this->parse($cron[3]);
    $this->dayOfWeek = $this->parse($cron[4]);

    return $expression;
  }

  private function parse($string)
  {
    if ($string === '*') {
      return $string;
    }

    $array = [];

    // Commas
    $values = explode(',', $string);
    if (count($values) !== 1) {
      $array = $values;
    }

    // Dash
    $values = explode('-', $string);
    if (count($values) === 2) {
      for ($i = intval($values[0]); $i <= intval($values[1]); $i++) { 
        $array[] = $i;
      }
    }

    return count($array) === 0 ? [$string] : $array;
  }

  public function isDue()
  {
    $now = DateTime::now();

    // Minute
    if (!$this->isNow($now['minute'], $this->minute)) {
      return false;
    }

    // Hour
    if (!$this->isNow($now['hour'], $this->hour)) {
      return false;
    }

    // Day
    if (!$this->isNow($now['day'], $this->day)) {
      return false;
    }

    // Month
    if (!$this->isNow($now['month'], $this->month)) {
      return false;
    }

    return true;
  }

  private function isNow($now, $schedule)
  {
    if ($schedule == '*') {
      return true;
    }

    return in_array($now, $schedule) ? true : false;
  }
}
