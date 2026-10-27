--TEST--
[HOST=] sections: SERVER_NAME matching a non-section entry or array-valued entries must not crash
--SKIPIF--
<?php include "skipif.inc"; ?>
--FILE--
<?php
include "include.inc";

$php = get_cgi_path();
reset_env_vars();

$dir = __DIR__ . "/ini_per_host_type_confusion";
@mkdir($dir);
$ini = "$dir/php.ini";
$script = "$dir/test.php";

file_put_contents($ini, <<<INI
date.timezone = UTC
precision = 14

[HOST=a.example]
precision = 5

[HOST=b.example]
foo[] = bar
precision = 7
INI);

file_put_contents($script, '<?php var_dump(ini_get("date.timezone"), ini_get("precision")); ?>');

foreach (["date.timezone", "DATE.TIMEZONE", "precision", "a.example", "b.example", "unknown.example"] as $host) {
    putenv("SERVER_NAME=$host");
    echo "--- $host\n";
    echo shell_exec("\"$php\" -q -c " . escapeshellarg($ini) . " " . escapeshellarg($script) . " 2>&1");
}
?>
--CLEAN--
<?php
$dir = __DIR__ . "/ini_per_host_type_confusion";
@unlink("$dir/php.ini");
@unlink("$dir/test.php");
@rmdir($dir);
?>
--EXPECT--
--- date.timezone
string(3) "UTC"
string(2) "14"
--- DATE.TIMEZONE
string(3) "UTC"
string(2) "14"
--- precision
string(3) "UTC"
string(2) "14"
--- a.example
string(3) "UTC"
string(1) "5"
--- b.example
string(3) "UTC"
string(1) "7"
--- unknown.example
string(3) "UTC"
string(2) "14"
