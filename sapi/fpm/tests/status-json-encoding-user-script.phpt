--TEST--
FPM: status json format escapes user, script and request method
--SKIPIF--
<?php include "skipif.inc"; ?>
--FILE--
<?php

require_once "tester.inc";

$cfg = <<<EOT
[global]
error_log = {{FILE:LOG}}
[unconfined]
listen = {{ADDR}}
pm = static
pm.max_children = 2
pm.status_path = /status
catch_workers_output = yes
EOT;

$code = <<<EOT
<?php
usleep(200000);
EOT;

$scriptDir = __DIR__ . '/status-json-encoding-"x":1,"y';
@mkdir($scriptDir);
$scriptFilename = $scriptDir . '/src.php';
file_put_contents($scriptFilename, $code);

$user = 'a"b\\c","pid":0,"z":"';
$method = 'G"E\\T';

$tester = new FPM\Tester($cfg, $code);
$tester->start();
$tester->expectLogStartNotices();
$responses = $tester
    ->multiRequest([
        [
            'headers' => [
                'REQUEST_METHOD' => $method,
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($user . ':x'),
                'SCRIPT_FILENAME' => $scriptFilename,
            ],
        ],
        ['uri' => '/status', 'query' => 'full&json', 'delay' => 100000],
    ]);
$data = json_decode($responses[1]->getBody('application/json'), true, flags: JSON_THROW_ON_ERROR);
$found = false;
foreach ($data['processes'] as $process) {
    if ($process['user'] === $user) {
        $found = true;
        var_dump($process['script'] === $scriptFilename);
        var_dump($process['request method'] === $method);
        var_dump(count($process));
    }
}
var_dump($found);
$tester->terminate();
$tester->expectLogTerminatingNotices();
$tester->close();

?>
Done
--EXPECT--
bool(true)
bool(true)
int(13)
bool(true)
Done
--CLEAN--
<?php
require_once "tester.inc";
FPM\Tester::clean();
$scriptDir = __DIR__ . '/status-json-encoding-"x":1,"y';
@unlink($scriptDir . '/src.php');
@rmdir($scriptDir);
?>
