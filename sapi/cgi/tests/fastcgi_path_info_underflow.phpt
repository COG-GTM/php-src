--TEST--
FastCGI: PATH_INFO shorter than the stripped SCRIPT_FILENAME suffix must not underflow (CVE-2019-11043 in php-cgi)
--SKIPIF--
<?php
include "skipif.inc";
if (PHP_OS_FAMILY === "Windows") die("skip not for Windows");
if (!function_exists("proc_open")) die("skip proc_open() not available");
?>
--FILE--
<?php
include "include.inc";

function fcgi_record(int $type, string $content): string {
    return pack("CCnnCC", 1, $type, 1, strlen($content), 0, 0) . $content;
}

function fcgi_len(int $len): string {
    return $len < 128 ? chr($len) : pack("N", $len | 0x80000000);
}

function fcgi_request(string $addr, array $params): string {
    $fp = stream_socket_client("tcp://$addr", $errno, $errstr, 5);
    if (!$fp) {
        return "connect failed: $errstr";
    }
    stream_set_timeout($fp, 10);
    $body = "";
    foreach ($params as $name => $value) {
        $body .= fcgi_len(strlen($name)) . fcgi_len(strlen($value)) . $name . $value;
    }
    $out = fcgi_record(1, pack("nCx5", 1, 0));
    foreach (str_split($body, 65535) as $chunk) {
        $out .= fcgi_record(4, $chunk);
    }
    $out .= fcgi_record(4, "") . fcgi_record(5, "");
    fwrite($fp, $out);

    $stdout = "";
    while (strlen($header = fread($fp, 8)) === 8) {
        $h = unpack("Cversion/Ctype/nid/nlen/Cpad/Creserved", $header);
        $content = $h["len"] ? stream_get_contents($fp, $h["len"]) : "";
        if ($h["pad"]) {
            fread($fp, $h["pad"]);
        }
        if ($h["type"] === 6) {
            $stdout .= $content;
        } elseif ($h["type"] === 3) {
            break;
        }
    }
    fclose($fp);
    $parts = explode("\r\n\r\n", $stdout, 2);
    return $parts[1] ?? "no response";
}

$php = get_cgi_path();
reset_env_vars();

$script = __DIR__ . "/fastcgi_path_info_underflow.php";
file_put_contents($script, '<?php var_dump($_SERVER["PATH_INFO"] ?? null);');

$srv = stream_socket_server("tcp://127.0.0.1:0");
$addr = stream_socket_get_name($srv, false);
fclose($srv);

$proc = proc_open(
    [$php, "-n", "-d", "cgi.fix_pathinfo=1", "-b", $addr],
    [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]],
    $pipes
);

for ($i = 0; $i < 50; $i++) {
    if ($fp = @stream_socket_client("tcp://$addr", $errno, $errstr, 1)) {
        fclose($fp);
        break;
    }
    usleep(100000);
}

$common = [
    "GATEWAY_INTERFACE" => "CGI/1.1",
    "REQUEST_METHOD"    => "GET",
    "REQUEST_URI"       => "/fastcgi_path_info_underflow.php",
    "DOCUMENT_ROOT"     => __DIR__,
    "HTTP_HUI"          => str_repeat("PTEST", 1000),
];

echo fcgi_request($addr, $common + [
    "SCRIPT_FILENAME" => $script . "/" . str_repeat("A", 35),
    "PATH_INFO"       => "",
]);
echo fcgi_request($addr, $common + [
    "SCRIPT_FILENAME" => $script . "/" . str_repeat("A", 35),
    "PATH_INFO"       => "/B",
]);
echo fcgi_request($addr, $common + [
    "SCRIPT_FILENAME" => $script . "/foo",
    "PATH_INFO"       => "/foo",
]);
echo fcgi_request($addr, $common + [
    "SCRIPT_FILENAME" => $script . "/foo/bar",
    "SCRIPT_NAME"     => "/fastcgi_path_info_underflow.php/foo/bar",
    "PATH_INFO"       => "/x/foo/bar",
]);

proc_terminate($proc);
foreach ($pipes as $p) {
    fclose($p);
}
proc_close($proc);
?>
--CLEAN--
<?php
@unlink(__DIR__ . "/fastcgi_path_info_underflow.php");
?>
--EXPECT--
string(0) ""
string(2) "/B"
string(4) "/foo"
string(8) "/foo/bar"
