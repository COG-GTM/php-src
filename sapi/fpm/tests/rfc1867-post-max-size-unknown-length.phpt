--TEST--
FPM: multipart body exceeding post_max_size without a known content length
--SKIPIF--
<?php include "skipif.inc"; ?>
--FILE--
<?php

require_once "tester.inc";

$uploadDir = __DIR__ . '/rfc1867-post-max-size-unknown-length-uploads';
@mkdir($uploadDir);

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
php_admin_value[html_errors] = false
php_admin_value[post_max_size] = 1K
php_admin_value[upload_tmp_dir] = {$uploadDir}
php_admin_flag[display_errors] = On
php_admin_flag[display_startup_errors] = On
php_admin_flag[log_errors] = On
EOT;

$code = <<<'EOT'
<?php
var_dump($_POST, $_FILES);
var_dump(glob(ini_get('upload_tmp_dir') . '/*'));
EOT;

$boundary = 'AaB03x';
$body = "--{$boundary}\r\n"
    . "Content-Disposition: form-data; name=\"file\"; filename=\"file.txt\"\r\n"
    . "Content-Type: text/plain\r\n\r\n"
    . "file contents\r\n"
    . "--{$boundary}\r\n"
    . "Content-Disposition: form-data; name=\"small\"\r\n\r\n"
    . "value\r\n"
    . "--{$boundary}\r\n"
    . "Content-Disposition: form-data; name=\"big\"\r\n\r\n"
    . str_repeat('a', 16 * 1024) . "\r\n"
    . "--{$boundary}--\r\n";

$tester = new FPM\Tester($cfg, $code);
$tester->start();
$tester->expectLogStartNotices();
/* A negative CONTENT_LENGTH makes FPM stream the whole body without a known
 * length, like the Apache SAPI does for chunked requests. */
echo $tester
    ->request(
        headers: [
            'CONTENT_TYPE' => "multipart/form-data; boundary={$boundary}",
            'CONTENT_LENGTH' => '-1',
        ],
        stdin: $body,
    )
    ->getBody();
$tester->terminate();
$tester->close();

?>
--EXPECT--
Warning: PHP Request Startup: Actual POST length does not match Content-Length, and exceeds 1024 bytes in Unknown on line 0
array(0) {
}
array(0) {
}
array(0) {
}
--CLEAN--
<?php
require_once "tester.inc";
FPM\Tester::clean();
$uploadDir = __DIR__ . '/rfc1867-post-max-size-unknown-length-uploads';
foreach (glob($uploadDir . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($uploadDir);
?>
