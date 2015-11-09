<?php

if ( ! defined('PROTECTED_LINKS_ADDON_NAME'))
{
	define('PROTECTED_LINKS_ADDON_NAME',         'Protected Links');
	define('PROTECTED_LINKS_ADDON_VERSION',      '3.0.0');
}

$config['name']=PROTECTED_LINKS_ADDON_NAME;
$config['version']=PROTECTED_LINKS_ADDON_VERSION;

$config['nsm_addon_updater']['versions_xml']='http://www.intoeetive.com/index.php/update.rss/30';