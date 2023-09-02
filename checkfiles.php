<?php
/**
*
* phpBB CheckFiles - Checks all relevant files of a phpBB installation for existence and integrity
*
* PHP: >=7.0.0,<8.3.0
*
* @copyright (c) 2023 LukeWCS <phpBB.de>
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
*/

$ver = '0.0.8';
$pin = '';

$special_files = [
	'config.php'			=> 'config.php',
	'install/'				=> preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, 'install/'),
	'language/de/'			=> preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, 'language/de/'),
	'language/de_x_sie/'	=> preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, 'language/de_x_sie/'),
];

$is_browser		= $_SERVER['HTTP_USER_AGENT'] ?? '' != '';
// $lf				= $is_browser ? "<br>" : "\n";
$lf				= "\n";
$checksum_file	= 'checkfiles.md5';
$contants_file	= 'includes/constants.php';
$start_time		= microtime(true);

if (file_exists($checksum_file))
{
	$checksums = file($checksum_file, FILE_IGNORE_NEW_LINES);
}
else
{
	echo "ERROR: checksum file '{$checksum_file}' not found{$lf}";
	exit;
}
if (file_exists($contants_file))
{
	preg_match('/@define\(\'PHPBB_VERSION\', \'(.*?)\'/', file_get_contents($contants_file), $matches);
	$PHPBB_VERSION = $matches[1];
}

if ($is_browser)
{
	echo "<!DOCTYPE HTML>{$lf}";
	echo "<html>{$lf}";
	echo "<head>{$lf}";
	echo "	<title>phpBB CheckFiles</title>{$lf}";
	echo "</head>{$lf}";
	echo "<body style=\"font-size: 1.1em;\">{$lf}";
	echo "<pre>{$lf}";
}

echo "phpBB CheckFiles v{$ver}{$lf}{$lf}";

echo "phpBB Version      : " . ($PHPBB_VERSION ?? '{unknown}') . "{$lf}";
echo "PHP Version        : " . PHP_VERSION . " (" . PHP_OS . "){$lf}";
echo "Client type        : " . ($is_browser ? 'Browser' : 'CLI') . "{$lf}";
echo "Files to be checked: " . count($checksums) . "{$lf}{$lf}";

echo "Please wait...{$lf}{$lf}";

flush();

$current_line = 0;
foreach ($checksums as $line) {
	$current_line++;
	preg_match('/([0-9a-f]{32}) [* ](.*)/', $line, $matches);
	if (is_array($matches) && count($matches) == 3)
	{
		$md5_saved = $matches[1];
		$file = $matches[2];
		$file = preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, $file);
		if (
			$file == $special_files['config.php']
			|| strpos($file, $special_files['install/']) === 0				&& !file_exists($special_files['install/'])
			|| strpos($file, $special_files['language/de/']) === 0			&& !file_exists($special_files['language/de/'])
			|| strpos($file, $special_files['language/de_x_sie/']) === 0	&& !file_exists($special_files['language/de_x_sie/'])
		) {
			continue;
		}

		if (file_exists($file))
		{
			$md5_calc = md5_file($file);
			if ($md5_saved != $md5_calc)
			{
				echo "CHANGED: {$file} (MD5#: $current_line){$lf}";
			}
		}
		else
		{
			echo "MISSING: {$file} (MD5#: $current_line){$lf}";
		}
	}
	else
	{
		echo "ERROR: invalid MD5 line '{$line}' (MD5#: $current_line){$lf}";
	}
}

$run_time = round(microtime(true) - $start_time, 3);

echo "{$lf}Finished! Run time: {$run_time}{$lf}";

if ($is_browser)
{
	echo "</pre>{$lf}";
	echo "</body>{$lf}";
	echo "</html>{$lf}";
}
