<?php
/**
*
* phpBB File Check - Checks all relevant files of a phpBB installation for existence and integrity
*
* PHP: >=7.1.0,<8.3.0
*
* @copyright (c) 2023 LukeWCS <phpBB.de>
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
*/

$ver					= '0.4.0';

$root_path				= __DIR__ . '/';
$checksum_file_name		= 'filecheck';
$checksum_file			= $checksum_file_name . '.md5';
$checksum_file_select	= 'manually';
$ignore_file			= 'filecheck_ignore.txt';
$exceptions_file		= 'filecheck_exceptions.txt';
$contants_file			= 'includes/constants.php';

$is_browser				= $_SERVER['HTTP_USER_AGENT'] ?? '' != '';
$lf						= "\n";
$unknown				= '{unknown}';
$output					= '';
$start_time				= microtime(true);

$ignored = [
	'config.php',
];
$exceptions = [
	'ext/phpbb/viglink/',
	'install/',
];

if (file_exists($root_path . $contants_file))
{
	preg_match('/\'PHPBB_VERSION\'.*?\'([0-9]+?\.[0-9]+?\.[0-9]+?)\'/', file_get_contents($root_path . $contants_file), $matches);
	$PHPBB_VERSION = $matches[1] ?? null;
	if ($PHPBB_VERSION !== null && !file_exists($root_path . $checksum_file))
	{
		$checksum_file			= $checksum_file_name . '_' . $PHPBB_VERSION . '.md5';
		$checksum_file_select	= 'auto';
	}
}

if (file_exists($root_path . $checksum_file))
{
	$checksums = file($root_path . $checksum_file, FILE_IGNORE_NEW_LINES);
	if ($checksums !== false)
	{
		preg_match('/([0-9]+?\.[0-9]+?\.[0-9]+)/', end($checksums), $matches);
		if (is_array($matches) && count($matches) == 2)
		{
			$checksums_ver	= array_pop($checksums);
		}
		$count_checksums	= count($checksums);
		$count_len			= strlen($count_checksums);
	}
}
else
{
	echo "ERROR: checksum file [{$checksum_file}] not found" . $lf;
	exit;
}

if (file_exists($root_path . $ignore_file))
{
	$ignored = array_merge($ignored, file($root_path . $ignore_file, FILE_IGNORE_NEW_LINES));
}

if (file_exists($root_path . $exceptions_file))
{
	$exceptions = array_merge($exceptions, file($root_path . $exceptions_file, FILE_IGNORE_NEW_LINES));
}

if ($is_browser)
{
	$output .= '<!DOCTYPE HTML>' . $lf;
	$output .= '<html>' . $lf;
	$output .= '<head>' . $lf;
	$output .= '	<title>phpBB File Check</title>' . $lf;
	$output .= '</head>' . $lf;
	$output .= '<body style="font-size: 1.1em;">' . $lf;
	$output .= '<pre>' . $lf;
}

$output .= "phpBB File Check v{$ver}" . $lf;
$output .= "==================" . str_repeat('=', strlen($ver)) . $lf;

$output .= $lf;
$output .= 'phpBB Version: ' . ($PHPBB_VERSION ?? $unknown) . $lf;
$output .= 'MD5 Version  : ' . ($checksums_ver ?? $unknown) . ' (' . $checksum_file_select . ')' . $lf;
$output .= 'PHP Version  : ' . PHP_VERSION . ' (' . PHP_OS . ')' . $lf;

$output .= $lf;
$output .= 'Please wait, ' . ($count_checksums ?? $unknown) . ' MD5 entries are being processed...' . $lf;

flush_buffer($output);

$line_num			= 0;
$count_missing		= 0;
$count_changed		= 0;
$count_error		= 0;
$count_ignored		= 0;
$count_valid_md5	= 0;
foreach ($checksums as $row)
{
	$line_num++;
	preg_match('/([0-9a-f]{32}) [* ](.*)/', $row, $matches);
	if (is_array($matches) && count($matches) == 3)
	{
		$count_valid_md5++;
		$md5_saved = $matches[1];
		$file = $matches[2];
		if (is_ignored($file, $ignored) || is_missing_but_exception($file, $exceptions))
		{
			$count_ignored++;
			continue;
		}

		if (file_exists($root_path . $file))
		{
			$md5_calc = md5_file($root_path . $file);
			if ($md5_saved != $md5_calc)
			{
				$output .= sprintf('{%1$ ' . $count_len . '.u} * CHANGED: [%2$s] (MD5: %3$s)', $line_num, $file, $md5_calc) . $lf;
				$count_changed++;
			}
		}
		else
		{
			$output .= sprintf('{%1$ ' . $count_len . '.u} ! MISSING: [%2$s]', $line_num, $file) . $lf;
			$count_missing++;
		}
	}
	else
	{
		$output .= sprintf('{%1$ ' . $count_len . '.u} ~ ERROR  : invalid MD5 row "%2$s"', $line_num, $row) . $lf;
		$count_error++;
	}
}

$len_list = array_map('strlen', explode($lf, $output));
$list_separator = str_repeat('-', max($len_list));
if ($list_separator != '')
{
	$output = $lf . $list_separator . $lf . $output . $list_separator . $lf ;
}

$output .= $lf;
$output .= sprintf('Valid MD5    : % ' . $count_len . '.u', $count_valid_md5) . $lf;
$output .= sprintf('Checked files: % ' . $count_len . '.u', $count_valid_md5 - $count_ignored) . $lf;
$output .= sprintf('Missing files: % ' . $count_len . '.u', $count_missing) . $lf;
$output .= sprintf('Changed files: % ' . $count_len . '.u', $count_changed) . $lf;
if ($count_error)
{
	$output .= sprintf('ERRORS total : % ' . $count_len . '.u', $count_error) . $lf;
}

$output .= $lf;
$output .= sprintf('Finished! Run time: %.3f seconds', microtime(true) - $start_time) . $lf;

if ($is_browser)
{
	$output .= '</pre>' . $lf;
	$output .= '</body>' . $lf;
	$output .= '</html>' . $lf;
}

flush_buffer($output);

function is_ignored(string $file, array &$ignore_list): bool
{
	foreach ($ignore_list as $ignored)
	{
		if (strpos($file, $ignored) === 0)
		{
			return true;
		}
	}

	return false;
}

function is_missing_but_exception(string $file, array &$exception_list): bool
{
	global $root_path;

	foreach ($exception_list as $exception)
	{
		if (strpos($file, $exception) === 0 && !file_exists($root_path . $exception))
		{
			return true;
		}
	}

	return false;
}

function flush_buffer(string &$text): void
{
	echo $text;
	$text = '';
	flush();
}
