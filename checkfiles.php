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

$ver = '0.1.1';
$pin = '';

$ignored_files = [
	'config.php',
];

// $exception_folders = [
	// 'install/'				=> preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, 'install/'),
	// 'language/de/'			=> preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, 'language/de/'),
	// 'language/de_x_sie/'	=> preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, 'language/de_x_sie/'),
// ];
$exception_folders = [
	'install/',
	'language/de/',
	'language/de_x_sie/',
];

$is_browser			= $_SERVER['HTTP_USER_AGENT'] ?? '' != '';
$lf					= "\n";
$checksum_file		= 'checkfiles.md5';
$contants_file		= 'includes/constants.php';
$checksums_count	= '{unknown}';
$checksums_ver		= '{unknown}';
$start_time			= microtime(true);

if (file_exists($checksum_file))
{
	$checksums = file($checksum_file, FILE_IGNORE_NEW_LINES);
	if ($checksums !== false)
	{
		$checksums_ver		= array_pop($checksums);
		$checksums_count	= count($checksums);
	}
}
else
{
	echo "ERROR: checksum file '{$checksum_file}' not found{$lf}";
	exit;
}

if (file_exists($contants_file))
{
	preg_match('/\'PHPBB_VERSION\'.*?\'(.*?)\'/', file_get_contents($contants_file), $matches);
	$PHPBB_VERSION = $matches[1] ?? '{unknown}';
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

echo "phpBB Version : " . $PHPBB_VERSION . "{$lf}";
echo "MD5 Version   : " . $checksums_ver . "{$lf}";
echo "PHP Version   : " . PHP_VERSION . " (" . PHP_OS . "){$lf}";
// echo "Client type  : " . ($is_browser ? 'Browser' : 'CLI') . "{$lf}";
// echo "Files to check: " . $checksums_count . "{$lf}{$lf}";

echo "{$lf}Please wait, we are checking {$checksums_count} files...{$lf}{$lf}";

flush();

$current_line = 0;
$count_missing = 0;
$count_changed = 0;
$count_error = 0;
foreach ($checksums as $line) {
	$current_line++;
	preg_match('/([0-9a-f]{32}) [* ](.*)/', $line, $matches);
	if (is_array($matches) && count($matches) == 3)
	{
		$md5_saved = $matches[1];
		$file = $matches[2];
		if (
			array_search($file, $ignored_files) !== false
			|| ignored_folder($file, $exception_folders)
		) {
			continue;
		}

		if (file_exists($file))
		{
			$md5_calc = md5_file($file);
			if ($md5_saved != $md5_calc)
			{
				echo sprintf('{%1$ 4.u} * CHANGED: [%2$s] (MD5: %3$s)', $current_line, $file, $md5_calc) . "{$lf}";
				$count_changed++;
			}
		}
		else
		{
			echo sprintf('{%1$ 4.u} ! MISSING: [%2$s]', $current_line, $file) . "{$lf}";
			$count_missing++;
		}
	}
	else
	{
		echo sprintf('{%1$ 4.u} ~ ERROR  : invalid MD5 row "%2$s"', $current_line, $line) . "{$lf}";
		$count_error++;
	}
}

echo "{$lf}";
echo "MISSING total: {$count_missing} {$lf}";
echo "CHANGED total: {$count_changed} {$lf}";
echo "ERROR total  : {$count_error} {$lf}";

$run_time = round(microtime(true) - $start_time, 3);

echo "{$lf}Finished! Run time: {$run_time} seconds{$lf}";

if ($is_browser)
{
	echo "</pre>{$lf}";
	echo "</body>{$lf}";
	echo "</html>{$lf}";
}

function ignored_folder($file, $exception_folders)
{
	foreach ($exception_folders as $folder)
	{
		if (strpos($file, $folder) === 0 && !file_exists($folder))
		{
			// var_dump($file, $folder);
			return true;
		}
	}
	return false;
}