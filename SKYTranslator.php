<?php

$usage = <<<USAGE
SKYTranslator takes master and cloned xml and then stdouts fixed clone,
              with all the elements in the same order, and with all
              the missing CDATA prepended with !FIXME! copied from master

php SKYTranslator.php [master.xml] [cloned.xml] > [stdout|new_clone_fixed.xml]
  master.xml is the master xml
  cloned.xml is the xml which should be diffed against master

USAGE;

$ERR = fopen('php://stderr','a');
if (count($argv)<3)
	die($usage);

$master = $argv[1];
if (!$M = file_get_contents($master))
	die("ERROR: cannot get contents of $master");

$cloned = $argv[1];
if (!$C = file_get_contents($cloned))
	die("ERROR: cannot get contents of $cloned");

xml_compare($M, $C);

function xml_compare($M, $C) {

	$MX = simplexml_load_string($M, 'SimpleXMLElement', LIBXML_NOCDATA);
	$json = json_encode($MX);
	$array = json_decode($json,TRUE);

	print_r($array);
}
