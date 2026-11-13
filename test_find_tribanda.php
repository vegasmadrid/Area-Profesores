<?php
$html = file_get_contents(sys_get_temp_dir() . '/web_colegio.html');
// Look for 5b766af in html
$pos = strpos($html, '5b766af');
echo substr($html, $pos - 200, 800);
