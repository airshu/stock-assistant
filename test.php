<?php

require_once('Stock.php');
$sb = new SmartBox();
$sb->setKeyWord($argv[1]);
// $sb->addStock();

// $sb->search();

$sb->listStock();

$sb->removeStock();

echo $sb->toxml();
