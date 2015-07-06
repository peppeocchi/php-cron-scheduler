<?php require_once __DIR__ . '/../vendor/autoload.php';

use GO\Scheduler;

$scheduler = new Scheduler();
// $res = $scheduler->php(__DIR__.'/command.php',[
//   '--foo' => 'bar',
//   '--baz' => '',
//   '-S' => '"Mi Awesome File"',
// ])->at('* * * * *');
$res = $scheduler->php(__DIR__.'/../src/GO/Job/*.php')->at('sa');
// $raw = $scheduler->raw('mycoolcommand')->at('10:00');
// $closure = $scheduler->call(function () {
//   return 28 + 1;
// })->at('25 May 2016 00:00');
var_dump($res);
