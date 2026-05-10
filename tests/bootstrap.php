<?php

$loader = require __DIR__.'/../../../vendor/autoload.php';

$loader->addPsr4('Lalalili\\CourseCore\\', __DIR__.'/../src/', true);
$loader->addPsr4('Lalalili\\CourseCore\\Tests\\', __DIR__.'/', true);

require __DIR__.'/Pest.php';

return $loader;
