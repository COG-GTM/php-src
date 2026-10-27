--TEST--
php-cgi -b creates the FastCGI Unix socket with mode 0660 (or PHP_FCGI_SOCKET_MODE)
--SKIPIF--
<?php
include "skipif.inc";
if (PHP_OS_FAMILY === 'Windows') die("skip not for Windows");
?>
--FILE--
<?php
include "include.inc";

function start_cgi(string $socket, ?string $mode): array {
    $env = getenv();
    unset($env['PHP_FCGI_SOCKET_MODE'], $env['PHP_FCGI_CHILDREN']);
    if ($mode !== null) {
        $env['PHP_FCGI_SOCKET_MODE'] = $mode;
    }
    $proc = proc_open(
        [get_cgi_path(), '-n', '-b', $socket],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $env
    );
    for ($i = 0; $i < 100; $i++) {
        clearstatcache();
        if (file_exists($socket) || !proc_get_status($proc)['running']) {
            break;
        }
        usleep(50000);
    }
    return [$proc, $pipes];
}

function stop_cgi($proc, array $pipes): string {
    proc_terminate($proc);
    stream_set_blocking($pipes[2], false);
    $err = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    foreach ($pipes as $p) {
        fclose($p);
    }
    proc_close($proc);
    return $err;
}

/* A permissive umask must not widen the socket permissions. */
umask(0);

foreach ([null, '0600', '0666', '640', 'abc', '0778', '01777', ''] as $mode) {
    $socket = sys_get_temp_dir() . '/fcgi_unix_socket_mode_' . getmypid() . '.sock';
    @unlink($socket);
    [$proc, $pipes] = start_cgi($socket, $mode);
    clearstatcache();
    echo var_export($mode, true), ': ';
    if (file_exists($socket)) {
        printf("%04o", fileperms($socket) & 0777);
        $client = @stream_socket_client("unix://$socket", $errno, $errstr, 1);
        echo $client ? " connectable\n" : " not connectable ($errstr)\n";
        if ($client) {
            fclose($client);
        }
        stop_cgi($proc, $pipes);
    } else {
        echo "no socket: ", trim(stop_cgi($proc, $pipes)), "\n";
    }
    @unlink($socket);
}
?>
--EXPECTF--
NULL: 0660 connectable
'0600': 0600 connectable
'0666': 0666 connectable
'640': 0640 connectable
'abc': no socket: %sPHP_FCGI_SOCKET_MODE must be an octal mode between 0000 and 0777%A
'0778': no socket: %sPHP_FCGI_SOCKET_MODE must be an octal mode between 0000 and 0777%A
'01777': no socket: %sPHP_FCGI_SOCKET_MODE must be an octal mode between 0000 and 0777%A
'': 0660 connectable
