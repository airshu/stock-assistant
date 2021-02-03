<?php

require_once('Stock.php');
$sb = new SmartBox();
$sb->setKeyWord($argv);
$sb->search();
echo $sb->toxml();
