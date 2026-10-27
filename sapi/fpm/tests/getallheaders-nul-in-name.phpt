--TEST--
FPM: getallheaders() with a NUL byte in a FastCGI parameter name
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
pm = dynamic
pm.max_children = 5
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 3
EOT;

$code = <<<EOT
<?php
echo "Test Start\n";
\$headers = getallheaders();
ksort(\$headers);
echo json_encode(\$headers), "\n";
echo "Test End\n";
EOT;

$tester = new FPM\Tester($cfg, $code);
$tester->start();
$tester->expectLogStartNotices();
$tester->request(
        '',
        [
            "HTTP_X\0" . str_repeat('A', 64) => 'nul',
            "HTTP_Y_\0" . str_repeat('B', 64) => 'underscore',
            'HTTP_X_FOO' => 'bar',
        ]
    )->expectBody(
        [
            'Test Start',
            '{"Content-Length":"0","Content-Type":"","X":"nul","X-Foo":"bar","Y-":"underscore"}',
            'Test End',
        ]
    );
$tester->terminate();
$tester->expectLogTerminatingNotices();
$tester->close();

?>
Done
--EXPECT--
Done
--CLEAN--
<?php
require_once "tester.inc";
FPM\Tester::clean();
?>
