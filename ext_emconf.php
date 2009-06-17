<?php

########################################################################
# Extension Manager/Repository config file for ext: "realty"
#
# Auto generated 17-06-2009 17:04
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Realty Manager',
	'description' => 'This extension provides a plugin that displays realty objects (immovables, properties, real estate), including an image gallery for each object.',
	'category' => 'plugin',
	'author' => 'Oliver Klee',
	'author_email' => 'typo3-coding@oliverklee.de',
	'shy' => 0,
	'dependencies' => 'cms,oelib,ameos_formidable',
	'conflicts' => 'dbal',
	'priority' => '',
	'module' => '',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => 1,
	'createDirs' => 'uploads/tx_realty/rte/',
	'modify_tables' => 'fe_users',
	'clearCacheOnLoad' => 1,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.3.3',
	'_md5_values_when_last_written' => 'a:65:{s:9:"ChangeLog";s:4:"c99f";s:31:"class.tx_realty_configcheck.php";s:4:"6569";s:21:"ext_conf_template.txt";s:4:"578d";s:12:"ext_icon.gif";s:4:"f073";s:17:"ext_localconf.php";s:4:"3af8";s:14:"ext_tables.php";s:4:"3202";s:14:"ext_tables.sql";s:4:"d28d";s:13:"locallang.xml";s:4:"475f";s:16:"locallang_db.xml";s:4:"06b7";s:7:"tca.php";s:4:"a47b";s:8:"todo.txt";s:4:"c7b3";s:36:"lib/class.tx_realty_cacheManager.php";s:4:"e962";s:44:"lib/class.tx_realty_domDocumentConverter.php";s:4:"c201";s:38:"lib/class.tx_realty_fileNameMapper.php";s:4:"a001";s:30:"lib/class.tx_realty_object.php";s:4:"8534";s:38:"lib/class.tx_realty_openImmoImport.php";s:4:"e309";s:34:"lib/class.tx_realty_translator.php";s:4:"8b8b";s:17:"lib/locallang.xml";s:4:"4d15";s:27:"lib/tx_realty_constants.php";s:4:"ab55";s:36:"lib/tx_realty_emailNotification.tmpl";s:4:"c378";s:40:"icons/icon_tx_realty_apartment_types.gif";s:4:"d517";s:35:"icons/icon_tx_realty_car_places.gif";s:4:"bb75";s:31:"icons/icon_tx_realty_cities.gif";s:4:"bfc0";s:35:"icons/icon_tx_realty_conditions.gif";s:4:"c6d7";s:34:"icons/icon_tx_realty_districts.gif";s:4:"5fc7";s:36:"icons/icon_tx_realty_house_types.gif";s:4:"e878";s:31:"icons/icon_tx_realty_images.gif";s:4:"e1a6";s:34:"icons/icon_tx_realty_images__h.gif";s:4:"a067";s:30:"icons/icon_tx_realty_items.gif";s:4:"475a";s:32:"icons/icon_tx_realty_objects.gif";s:4:"f073";s:35:"icons/icon_tx_realty_objects__h.gif";s:4:"a523";s:29:"icons/icon_tx_realty_pets.gif";s:4:"57cd";s:14:"doc/manual.sxw";s:4:"7a1e";s:14:"pi1/ce_wiz.gif";s:4:"fe10";s:35:"pi1/class.tx_realty_contactForm.php";s:4:"5543";s:34:"pi1/class.tx_realty_filterForm.php";s:4:"fc70";s:38:"pi1/class.tx_realty_frontEndEditor.php";s:4:"f1be";s:36:"pi1/class.tx_realty_frontEndForm.php";s:4:"4f37";s:43:"pi1/class.tx_realty_frontEndImageUpload.php";s:4:"c7bc";s:27:"pi1/class.tx_realty_pi1.php";s:4:"394f";s:37:"pi1/class.tx_realty_pi1_Formatter.php";s:4:"5a3c";s:35:"pi1/class.tx_realty_pi1_wizicon.php";s:4:"3388";s:23:"pi1/flexform_pi1_ds.xml";s:4:"7182";s:17:"pi1/locallang.xml";s:4:"826a";s:17:"pi1/submit_bg.gif";s:4:"9359";s:32:"pi1/tx_realty_frontEndEditor.xml";s:4:"e71a";s:37:"pi1/tx_realty_frontEndImageUpload.xml";s:4:"072d";s:25:"pi1/tx_realty_pi1.tpl.css";s:4:"8614";s:25:"pi1/tx_realty_pi1.tpl.htm";s:4:"869b";s:33:"pi1/images/button_act_bg_left.png";s:4:"576e";s:34:"pi1/images/button_act_bg_right.png";s:4:"b2d7";s:29:"pi1/images/button_bg_left.png";s:4:"43d8";s:30:"pi1/images/button_bg_right.png";s:4:"63f6";s:30:"pi1/images/cityselector_bg.png";s:4:"11bc";s:32:"pi1/images/cityselector_head.png";s:4:"4106";s:24:"pi1/images/fav_arrow.png";s:4:"de5e";s:25:"pi1/images/fav_button.png";s:4:"91ae";s:23:"pi1/images/page_act.png";s:4:"02fe";s:22:"pi1/images/page_no.png";s:4:"a172";s:28:"pi1/images/search_button.png";s:4:"0f4c";s:26:"pi1/images/sort_button.png";s:4:"e6b0";s:24:"pi1/static/constants.txt";s:4:"acd8";s:24:"pi1/static/editorcfg.txt";s:4:"7c17";s:20:"pi1/static/setup.txt";s:4:"176d";s:27:"cli/class.tx_realty_cli.php";s:4:"8135";}',
	'constraints' => array(
		'depends' => array(
			'php' => '5.2.0-0.0.0',
			'typo3' => '4.1.2-0.0.0',
			'cms' => '',
			'oelib' => '0.4.3-',
			'ameos_formidable' => '1.1.0-1.9.99',
		),
		'conflicts' => array(
			'dbal' => '',
		),
		'suggests' => array(
			'mailform_userfunc' => '0.0.3.-',
		),
	),
	'suggests' => array(
		'mailform_userfunc' => '0.0.3.-',
	),
);

?>