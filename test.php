<?php
require './filters/HugeFilter.php';
$filter = new HugeFilter();
$xml = new QbSimplexmlElement('<?xml version="1.0" encoding="US-ASCII"?>
<QBXML>
<ClassAddRq>
<ClassAdd><Name>FooBar1 &#150; WTF</Name></ClassAdd>
<ClassAdd><Name>FooBar2 &#150; WTF</Name></ClassAdd>
<ClassAdd><Name>FooBar3 &#150; WTF</Name></ClassAdd>
<ClassAdd><Name>FooBar4 &#150; WTF</Name></ClassAdd>
</ClassAddRq>
</QBXML>');
echo "===========\n";
echo $xml->asNiceXml()."\n";
echo "===========\n";
echo $filter->getXmlAscii($xml->ClassAddRq)."\n";
echo "===========\n";
$xml->truncate('ClassAddRq/ClassAdd/Name',7);
echo $filter->getXmlAscii($xml->ClassAddRq)."\n";
