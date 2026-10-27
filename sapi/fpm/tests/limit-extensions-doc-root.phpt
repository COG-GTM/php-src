--TEST--
FPM: security.limit_extensions is applied to the script opened through doc_root
--SKIPIF--
<?php include "skipif.inc"; ?>
--FILE--
<?php

require_once "tester.inc";

$docRoot = __DIR__;
$cfg = <<<EOT
[global]
error_log = {{FILE:LOG}}
[unconfined]
listen = {{ADDR}}
pm = static
pm.max_children = 1
security.limit_extensions = .php
php_admin_value[doc_root] = $docRoot
EOT;

$code = <<<EOT
<?php
echo "front controller";
EOT;

$upload = __DIR__ . '/limit-extensions-doc-root.upload.jpg';
file_put_contents($upload, "<?php echo 'executed';");

$tester = new FPM\Tester($cfg, $code);
[$sourceFilePath, $scriptName] = $tester->createSourceFileAndScriptName();
$tester->start();
$tester->expectLogStartNotices();
$tester
    ->request(scriptFilename: $sourceFilePath, scriptName: $scriptName)
    ->expectBody('front controller');
$tester
    ->request(
        uri: '/' . basename($upload),
        scriptFilename: $sourceFilePath,
        scriptName: '/' . basename($upload),
    )
    ->expectStatus('403 Forbidden')
    ->expectBody('Access denied.');
$tester->expectLogPattern(
    '/Access to the script \'' . preg_quote($upload, '/') . '\' has been denied \(see security.limit_extensions\)/'
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
@unlink(__DIR__ . '/limit-extensions-doc-root.upload.jpg');
?>
