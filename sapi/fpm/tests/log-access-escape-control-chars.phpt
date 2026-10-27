--TEST--
FPM: access log escapes control characters in request-derived fields
--SKIPIF--
<?php include "skipif.inc"; ?>
--FILE--
<?php

require_once "tester.inc";

$src = <<<EOT
<?php
echo "OK";
EOT;

$cfg = <<<EOT
[global]
error_log = {{FILE:LOG:ERR}}
[unconfined]
listen = {{ADDR}}
access.log = {{FILE:LOG:ACC}}
access.format = "%u|%m|%r|%q|%{HTTP_X_TEST}e"
pm = static
pm.max_children = 1
EOT;

$tester = new FPM\Tester($cfg, $src);
$tester->start();
$tester->expectLogStartNotices();
$tester->request(
    query: "a=1\r\n127.0.0.1 - admin \"GET /admin\" 200",
    headers: [
        'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode("bob\n127.0.0.1 - admin [fake entry]:x"),
        'HTTP_X_TEST' => "t\tv\x7f",
    ],
)->expectBody('OK');
$tester->expectAccessLog(
    'bob\x0a127.0.0.1 - admin [fake entry]|GET|'
    . '/log-access-escape-control-chars.src.php?a=1\x0d\x0a127.0.0.1 - admin "GET /admin" 200|'
    . 'a=1\x0d\x0a127.0.0.1 - admin "GET /admin" 200|'
    . 't\x09v\x7f'
);
$tester->request(headers: ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('alice:x')])->expectBody('OK');
$tester->expectAccessLog('alice|GET|/log-access-escape-control-chars.src.php||-');
$tester->terminate();
$tester->expectLogTerminatingNotices();
$tester->close();
$tester->checkAccessLog();

?>
Done
--EXPECT--
Done
--CLEAN--
<?php
require_once "tester.inc";
FPM\Tester::clean();
?>
