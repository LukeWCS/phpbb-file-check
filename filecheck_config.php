<?php
/**
*
* phpBB File Check - Checks all relevant files of a phpBB installation for existence and integrity
*
* @copyright (c) 2023 LukeWCS <phpBB.de>
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
*/

# phpcs:set VariableAnalysis.CodeAnalysis.VariableAnalysis validUnusedVariableNames config

/*
	DO NOT MAKE ANY CHANGES HERE UNLESS YOU HAVE RECEIVED INSTRUCTIONS TO DO SO!
*/
$config = [
	'debug_mode'		=> 0,
	'zip_url_pattern'	=> 'https://downloads.phpbb.de/pakete/deutsch/{MAJOR}.{MINOR}/{MAJOR}.{MINOR}.{PATCH}/',
	'zip_name_pattern'	=> 'phpBB-{MAJOR}.{MINOR}.{PATCH}-deutsch-FileCheck-MD5.zip',
];

# phpcs:set VariableAnalysis.CodeAnalysis.VariableAnalysis validUnusedVariableNames
