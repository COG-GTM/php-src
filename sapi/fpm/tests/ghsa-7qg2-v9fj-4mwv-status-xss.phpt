--TEST--
FPM: GHSA-7qg2-v9fj-4mwv - status xss
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

$scriptDir = __DIR__ . '/ghsa-7qg2-v9fj-4mwv-<svg onload=alert(3)>';
@mkdir($scriptDir);
$scriptFilename = $scriptDir . '/src.php';
file_put_contents($scriptFilename, $code);

$injectedRequest = [
    'uri' => '/<script>alert(1)</script>',
    'query' => '<script>alert(2)</script>',
    'headers' => [
        'REQUEST_METHOD' => '<i>GET</i>',
        'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('<script>alert(4)</script>:x'),
        'SCRIPT_FILENAME' => $scriptFilename,
    ],
];

$tester = new FPM\Tester($cfg, $code);
$tester->start();
$tester->expectLogStartNotices();
foreach (['html' => 'text/html', 'xml' => 'text/xml'] as $format => $contentType) {
    $responses = $tester
            ->multiRequest([
                    $injectedRequest,
                    ['uri' => '/status', 'query' => "full&$format", 'delay' => 100000],
            ]);
    $body = $responses[1]->getBody($contentType);
    echo "$format:\n";
    var_dump(strpos($body, '<script>'));
    var_dump(strpos($body, '<svg'));
    var_dump(strpos($body, '<i>'));
    var_dump(str_contains($body, '&lt;script&gt;alert(4)&lt;/script&gt;'));
    var_dump(str_contains($body, '&lt;svg onload=alert(3)&gt;'));
    var_dump(str_contains($body, '&lt;i&gt;GET&lt;/i&gt;'));
}
$tester->terminate();
$tester->expectLogTerminatingNotices();
$tester->close();

?>
Done
--EXPECT--
html:
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
xml:
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
Done
--CLEAN--
<?php
require_once "tester.inc";
FPM\Tester::clean();
$scriptDir = __DIR__ . '/ghsa-7qg2-v9fj-4mwv-<svg onload=alert(3)>';
@unlink($scriptDir . '/src.php');
@rmdir($scriptDir);
?>
