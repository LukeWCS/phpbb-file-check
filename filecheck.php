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

$ver = '0.3.0';

$ignored = [
	'config.php',
];
$exceptions = [
	'ext/phpbb/viglink/',
	'install/',
];

$is_browser				= $_SERVER['HTTP_USER_AGENT'] ?? '' != '';
$lf						= "\n";
$checksum_file_name		= 'filecheck';
$checksum_file			= $checksum_file_name . '.md5';
$checksum_file_select	= 'manually';
$ignore_file			= 'filecheck_ignore.txt';
$exceptions_file		= 'filecheck_exceptions.txt';
$contants_file			= 'includes/constants.php';
$start_time				= microtime(true);
$output					= '';

if (file_exists($contants_file))
{
	preg_match('/\'PHPBB_VERSION\'.*?\'([0-9]+?\.[0-9]+?\.[0-9]+?)\'/', file_get_contents($contants_file), $matches);
	$PHPBB_VERSION = $matches[1] ?? null;
	if ($PHPBB_VERSION !== null && !file_exists($checksum_file))
	{
		$checksum_file			= $checksum_file_name . '_' . $PHPBB_VERSION . '.md5';
		$checksum_file_select	= 'auto';
	}
}

if (file_exists($checksum_file))
{
	$checksums = file($checksum_file, FILE_IGNORE_NEW_LINES);
	if ($checksums !== false)
	{
		$checksums_ver		= array_pop($checksums);
		$checksums_count	= count($checksums);
		$count_len			= strlen($checksums_count);
	}
}
else
{
	echo "ERROR: checksum file [{$checksum_file}] not found" . $lf;
	exit;
}

if (file_exists($ignore_file))
{
	$ignored = array_merge($ignored, file($ignore_file, FILE_IGNORE_NEW_LINES));
}

if (file_exists($exceptions_file))
{
	$exceptions = array_merge($exceptions, file($exceptions_file, FILE_IGNORE_NEW_LINES));
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

$output .= $lf;
$output .= 'phpBB Version: ' . ($PHPBB_VERSION ?? '{unknown}') . $lf;
$output .= 'MD5 Version  : ' . ($checksums_ver ?? '{unknown}') . ' (' . $checksum_file_select . ')' . $lf;
$output .= 'PHP Version  : ' . PHP_VERSION . ' (' . PHP_OS . ')' . $lf;

$output .= $lf;
$output .= 'Please wait, we are checking ' . ($checksums_count ?? '{unknown}') . ' files...' . $lf;

flush_buffer($output);

$line_num		= 0;
$count_missing	= 0;
$count_changed	= 0;
$count_error	= 0;
foreach ($checksums as $row)
{
	$line_num++;
	preg_match('/([0-9a-f]{32}) [* ](.*)/', $row, $matches);
	if (is_array($matches) && count($matches) == 3)
	{
		$md5_saved = $matches[1];
		$file = $matches[2];
		if (is_ignored($file, $ignored) || is_missing_but_exception($file, $exceptions))
		{
			continue;
		}

		if (file_exists($file))
		{
			$md5_calc = md5_file($file);
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

flush_buffer($output);

$output .= sprintf('MISSING total: % ' . $count_len . '.u', $count_missing) . $lf;
$output .= sprintf('CHANGED total: % ' . $count_len . '.u', $count_changed) . $lf;
$output .= sprintf('ERRORS total : % ' . $count_len . '.u', $count_error) . $lf;

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
	foreach ($exception_list as $exception)
	{
		if (strpos($file, $exception) === 0 && !file_exists($exception))
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
