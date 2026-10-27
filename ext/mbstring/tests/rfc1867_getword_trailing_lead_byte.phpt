--TEST--
Multipart Content-Disposition value ending in a truncated multibyte lead byte must not be read past its end
--EXTENSIONS--
mbstring
--INI--
file_uploads=1
mbstring.encoding_translation=1
input_encoding=UTF-8
internal_encoding=UTF-8
--POST_RAW--
Content-Type: multipart/form-data; boundary=---------------------------7d3a1c2f0b9e
-----------------------------7d3a1c2f0b9e
Content-Disposition: form-data; name="field"; x=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAð

value
-----------------------------7d3a1c2f0b9e
Content-Disposition: form-data; name="upload"; filename="f.txt"; x=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAð
Content-Type: text/plain

contents
-----------------------------7d3a1c2f0b9e--
--FILE--
<?php
var_dump(array_keys($_POST), $_POST["field"]);
var_dump(array_keys($_FILES), $_FILES["upload"]["name"], $_FILES["upload"]["error"]);
?>
--EXPECT--
array(1) {
  [0]=>
  string(5) "field"
}
string(5) "value"
array(1) {
  [0]=>
  string(6) "upload"
}
string(5) "f.txt"
int(0)
