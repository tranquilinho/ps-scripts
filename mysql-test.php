<?php
$link = mysqli_connect('127.0.0.1', 'wordpress', 'mypassword', 'wordpress');

if (!$link) {
    die('Connect Error (' . mysqli_connect_errno() . ') '
            . mysqli_connect_error());
}

echo 'Connected... ' . mysqli_get_host_info($link) . "\n";

?>