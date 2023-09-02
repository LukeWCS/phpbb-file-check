<?php
/**
*
* phpBB File Check - Checks all relevant files of a phpBB installation for existence and integrity
*
* PHP: >=7.1.0,<8.3.0
*
* @copyright (c) 2023, LukeWCS (phpBB.de)
*
*/

$debug_mode				= false;

$root_path				= __DIR__ . '/';
$checksum_file_name		= 'filecheck';
$checksum_file_suffix	= '.md5';
$checksum_file			= $checksum_file_name . $checksum_file_suffix;
$checksum_select_mode	= 'manually';
$ignore_file			= 'filecheck_ignore.txt';
$exceptions_file		= 'filecheck_exceptions.txt';
$contants_file			= 'includes/constants.php';

$ver					= '1.0.0';
$title					= "phpBB File Check v{$ver}";
$is_browser				= $_SERVER['HTTP_USER_AGENT'] ?? '' != '';
$lf						= "\n";
$unknown				= '{unknown}';
$msg_type_len			= $debug_mode ? 9 : 7;
$output					= '';
$start_time				= microtime(true);

$ignored = [
	'^config.php',
	'^\.git|\/\.git',
];
$exceptions = [
	'docs/',
	'ext/phpbb/viglink/',
	'install/',
];

if ($is_browser)
{
	$output .= html_start();
}

$output .= $title . ($debug_mode ? ' (DEBUG MODE)' : '') . $lf;
$output .= str_repeat('=', strlen($title)) . $lf . $lf;;

if (file_exists($root_path . $contants_file))
{
	preg_match('/\'PHPBB_VERSION\'.*?\'([0-9]+?\.[0-9]+?\.[0-9]+?)\'/', file_get_contents($root_path . $contants_file), $matches);
	$PHPBB_VERSION = $matches[1] ?? null;
	if ($PHPBB_VERSION !== null && !file_exists($root_path . $checksum_file))
	{
		$checksum_file			= $checksum_file_name . '_' . $PHPBB_VERSION . $checksum_file_suffix;
		$checksum_select_mode	= 'auto';
	}
}

if (file_exists($root_path . $checksum_file))
{
	$checksums = file($root_path . $checksum_file, FILE_IGNORE_NEW_LINES);
	if ($checksums !== false)
	{
		preg_match('/^([0-9]+?\.[0-9]+?\.[0-9]+)/', end($checksums), $matches);
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
	$output .= "ERROR: checksum file [{$checksum_file}] not found" . $lf;
	$output .= html_end();
	flush_buffer($output);
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

$output .= 'phpBB Version: ' . ($PHPBB_VERSION ?? $unknown) . $lf;
$output .= 'MD5 Version  : ' . ($checksums_ver ?? $unknown) . ' (' . $checksum_select_mode . ')' . $lf;
$output .= 'PHP Version  : ' . PHP_VERSION . ' (' . PHP_OS . ')' . $lf;

$output .= $lf;
$output .= 'Please wait, ' . ($count_checksums ?? $unknown) . ' checksums are being processed...' . $lf;

flush_buffer($output);

$line_num			= 0;
$count_missing		= 0;
$count_changed		= 0;
$count_error		= 0;
$count_ignored		= 0;
$count_exceptions	= 0;
$count_excluded		= 0;
$count_valid_hash	= 0;
foreach ($checksums as $row)
{
	$line_num++;
	preg_match('/^([0-9a-f]{32}) [* ](.+)/', $row, $matches);
	if (is_array($matches) && count($matches) == 3)
	{
		$count_valid_hash++;
		$hash_saved = $matches[1];
		$file = $matches[2];
		$is_ignored = is_ignored($file, $ignored);
		if ($is_ignored || is_missing_but_exception($file, $exceptions))
		{
			if ($debug_mode)
			{
				if ($is_ignored)
				{
					$output .= sprintf('{%1$ ' . $count_len . '.u} - %2$ -' . $msg_type_len . 's: [%3$s]', $line_num, 'IGNORED', $file) . $lf;
					$count_ignored++;
				}
				else
				{
					$output .= sprintf('{%1$ ' . $count_len . '.u} - %2$ -' . $msg_type_len . 's: [%3$s]', $line_num, 'EXCEPTION', $file) . $lf;
					$count_exceptions++;
				}
			}
			$count_excluded++;
			continue;
		}

		if (file_exists($root_path . $file))
		{
			$hash_calc = md5_file($root_path . $file);
			if ($hash_saved != $hash_calc)
			{
				$output .= sprintf('{%1$ ' . $count_len . '.u} * %2$ -' . $msg_type_len . 's: [%3$s] (hash: %4$s)', $line_num, 'CHANGED', $file, $hash_calc) . $lf;
				$count_changed++;
			}
		}
		else
		{
			$output .= sprintf('{%1$ ' . $count_len . '.u} ! %2$ -' . $msg_type_len . 's: [%3$s]', $line_num, 'MISSING', $file) . $lf;
			$count_missing++;
		}
	}
	else
	{
		$output .= sprintf('{%1$ ' . $count_len . '.u} ~ %2$ -' . $msg_type_len . 's: invalid hash row "%3$s"', $line_num, 'ERROR', $row) . $lf;
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
$output .= sprintf('Valid hashes : % ' . $count_len . '.u', $count_valid_hash) . $lf;
if ($debug_mode)
{
	$output .= sprintf('Ignored      : % ' . $count_len . '.u', $count_ignored) . $lf;
	$output .= sprintf('Exceptions   : % ' . $count_len . '.u', $count_exceptions) . $lf;
}
$output .= sprintf('Checked files: % ' . $count_len . '.u', $count_valid_hash - $count_excluded) . $lf;
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
	$output .= html_end();
}

flush_buffer($output);

function is_ignored(string $file, array &$ignore_list): bool
{
	foreach ($ignore_list as $ignored)
	{
		$regex = @preg_match('/' . $ignored . '/', $file);
		if ($regex !== false && $regex)
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

function html_start(): string
{
	global $lf;

	$output = '';
	$output .= '<!DOCTYPE HTML>' . $lf;
	$output .= '<html>' . $lf;
	$output .= '<head>' . $lf;
	$output .= '	<title>phpBB File Check</title>' . $lf;
	$output .= '</head>' . $lf;
	$output .= '<body style="font-size: 1.1em;">' . $lf;
	$output .= '<pre>' . $lf;

	return $output;
}

function html_end(): string
{
	global $lf;

	$output = '';
	$output .= '</pre>' . $lf;
	$output .= '</body>' . $lf;
	$output .= '</html>' . $lf;

	return $output;
}
