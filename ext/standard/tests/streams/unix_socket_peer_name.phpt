--TEST--
Peer name of Unix domain sockets is bounded by the address length returned by the kernel
--SKIPIF--
<?php
if (!strncasecmp(PHP_OS, 'WIN', 3)) die("skip Unix only");
if (!in_array('unix', stream_get_transports())) die("skip unix transport not available");
if (!in_array('udg', stream_get_transports())) die("skip udg transport not available");
?>
--FILE--
<?php
$base = sys_get_temp_dir() . '/unix_socket_peer_name_' . getmypid();

/* accept() from an unbound stream client */
$srvPath = "$base.srv.sock";
$server = stream_socket_server("unix://$srvPath", $errno, $errstr);
$client = stream_socket_client("unix://$srvPath");
$conn = stream_socket_accept($server, 1, $peer);
var_dump($peer);
var_dump(stream_socket_get_name($client, true) === $srvPath);
fclose($conn);
fclose($client);
fclose($server);
unlink($srvPath);

/* recvfrom() on a datagram socket from an unbound sender */
$dgPath = "$base.dg.sock";
$receiver = stream_socket_server("udg://$dgPath", $errno, $errstr, STREAM_SERVER_BIND);
$sender = stream_socket_client("udg://$dgPath");
fwrite($sender, "ping");
var_dump(stream_socket_recvfrom($receiver, 16, 0, $peer));
var_dump($peer);
fclose($sender);
fclose($receiver);
unlink($dgPath);
?>
--EXPECT--
string(0) ""
bool(true)
string(4) "ping"
string(0) ""
