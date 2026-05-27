<?php
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'OFF') . "<br>";
echo "Porta: " . $_SERVER['SERVER_PORT'] . "<br>";
echo "Host: " . $_SERVER['HTTP_HOST'] . "<br>";
echo "IP Real: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);