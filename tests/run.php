<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require dirname(__DIR__) . '/functions.php';

$files = glob(__DIR__ . '/*_test.php');
sort($files);
foreach ($files as $file) {
    printf("\n== %s ==\n", basename($file, '_test.php'));
    require $file;
}
exit(t_summary());
