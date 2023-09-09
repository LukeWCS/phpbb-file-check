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

# phpcs:disable PSR1.Files.SideEffects

/*
* Initialization
*/
define('EOL'			, "\n");
define('VALID_CHARS'	, 'a-zA-Z0-9\/\-_.');
define('IS_BROWSER'		, $_SERVER['HTTP_USER_AGENT'] ?? '' != '');

$debug_mode				= false;

$root_path				= __DIR__ . '/';
$checksum_file_name		= 'filecheck';
$checksum_file_suffix	= '.md5';
$checksum_file			= $checksum_file_name . $checksum_file_suffix;
$checksum_diff_file		= $checksum_file_name . '_diff' . $checksum_file_suffix;
$checksum_select_mode	= 'MANUALLY';
$ignore_file			= 'filecheck_ignore.txt';
$exceptions_file		= 'filecheck_exceptions.txt';
$constants_file			= 'includes/constants.php';

$ver					= '1.1.2';
$title					= "phpBB File Check v{$ver}";
$unknown				= '{unknown}';
$output					= html_start();
$start_time				= microtime(true);

$ignore_list = [
	'/^\.git|\/\.git/',
];

$exceptions_list = [
	'docs/',
	'ext/phpbb/viglink/',
	'install/',
];

/*
* Generate title
*/
$output .= $title . ($debug_mode ? ' (DEBUG MODE)' : '') . EOL;
$output .= str_repeat('=', strlen($title)) . EOL . EOL;

/*
* Get the phpBB version from constants.php
*/
if (file_exists($root_path . $constants_file))
{
	$constants_content = @file_get_contents($root_path . $constants_file);
	if ($constants_content !== false)
	{
		preg_match('/\'PHPBB_VERSION\'\s*,\s*\'([0-9]+\.[0-9]+\.[0-9]+)\'/', $constants_content, $matches);
		$PHPBB_VERSION = $matches[1] ?? null;
		if ($PHPBB_VERSION !== null && !file_exists($root_path . $checksum_file))
		{
			$checksum_file			= $checksum_file_name . '_' . $PHPBB_VERSION . $checksum_file_suffix;
			$checksum_diff_file		= $checksum_file_name . '_' . $PHPBB_VERSION . '_diff' . $checksum_file_suffix;
			$checksum_select_mode	= 'AUTO';
		}
		if ($PHPBB_VERSION === null)
		{
			$constants_notice = "NOTICE: phpBB version could not be determined from [{$constants_file}]" . EOL;
		}
	}
	else
	{
		$constants_notice = "NOTICE: phpBB file [{$constants_file}] could not be read" . EOL;
	}
}
else
{
	$constants_notice = "NOTICE: phpBB file [{$constants_file}] not found" . EOL;
}

/*
* Load the checksum files into the hash list
*/
$checksums_name			= '';
$checksums_ver			= '';
$checksums_diff_name	= '';
$checksums_diff_ver		= '';
$hash_list				= [];
if (file_exists($root_path . $checksum_file))
{
	load_checksum_file($root_path . $checksum_file, $checksums_name, $checksums_ver, 1, $hash_list);
}
else
{
	$output .= $constants_notice ?? '';
	terminate("checksum file [{$checksum_file}] not found");
}

if (file_exists($root_path . $checksum_diff_file))
{
	load_checksum_file($root_path . $checksum_diff_file, $checksums_diff_name, $checksums_diff_ver, 2, $hash_list);
}

$count_checksums		= count($hash_list);
$checksums_count_len	= strlen($count_checksums);

/*
* Load and check the external ignore list
*/
if (file_exists($root_path . $ignore_file))
{
	$import_list	= @file($root_path . $ignore_file, FILE_IGNORE_NEW_LINES);
	$line_num		= 0;
	$error_messages	= '';
	if ($import_list !== false)
	{
		foreach ($import_list as $row)
		{
			$line_num++;
			if (@preg_match($row, '') === false)
			{
				$error_messages .= "line {$line_num}: [{$row}] contains invalid RegEx" . EOL;
			}
		}
		if ($error_messages != '')
		{
			add_list_lines($error_messages);
			terminate("ignore list [{$ignore_file}] has the following issues:" . EOL . $error_messages);
		}
		$ignore_list = array_merge($ignore_list, $import_list);
	}
	else
	{
		terminate("ignore list [{$ignore_file}] could not be read");
	}
}

/*
* Load and check the external exception list
*/
if (file_exists($root_path . $exceptions_file))
{
	$import_list = @file($root_path . $exceptions_file, FILE_IGNORE_NEW_LINES);
	$line_num		= 0;
	$error_messages	= '';
	if ($import_list !== false)
	{
		foreach ($import_list as $row)
		{
			$line_num++;
			if (preg_match('/[^' . VALID_CHARS . ']/', $row))
			{
				$error_messages .= "line {$line_num}: [{$row}] contains invalid characters" . EOL;
			}
		}
		if ($error_messages != '')
		{
			add_list_lines($error_messages);
			terminate("exception list [{$exceptions_file}] has the following issues:" . EOL . $error_messages);
		}
		$exceptions_list = array_merge($exceptions_list, $import_list);
	}
	else
	{
		terminate("exception list [{$exceptions_file}] could not be read");
	}
}

/*
* Display: versions and number of checksums
*/
$output .= sprintf('phpBB Version: %1$s', $PHPBB_VERSION ?? $unknown) . EOL;
$output .= sprintf('MD5 Version 1: %1$s (%2$s) %3$s', $checksums_ver, $checksums_name, $checksum_select_mode) . EOL;
if (file_exists($root_path . $checksum_diff_file))
{
	$output .= sprintf('MD5 Version 2: %1$s (%2$s) %3$s', $checksums_diff_ver, $checksums_diff_name, $checksum_select_mode) . EOL;
}
$output .= sprintf('PHP Version  : %1$s (%2$s)', PHP_VERSION, PHP_OS) . EOL;

$output .= EOL;
$output .= 'Please wait, ' . $count_checksums . ' checksums are being processed...' . EOL;

flush_buffer();

/*
* The core - processing checksums
*/
$count_missing		= 0;
$count_changed		= 0;
$count_error		= 0;
$count_warning		= 0;
$count_notice		= 0;
$count_ignored		= 0;
$count_exceptions	= 0;
$count_checked		= 0;
$results_list		= [];
foreach ($hash_list as $file => $hash_data)
{
	$is_ignored = is_ignored($file, $ignore_list);
	if ($is_ignored || is_exception($file, $exceptions_list, $root_path))
	{
		if ($debug_mode)
		{
			if ($is_ignored)
			{
				$count_ignored++;
				$results_list[] = [
					'path'			=> dirname($file),
					'hash_file_id'	=> $hash_data[0]['hash_file_id'],
					'hash_line_num'	=> $hash_data[0]['hash_line_num'],
					'msg_type'		=> '- IGNORED',
					'file'			=> $file,
					'msg'			=> '',
				];
			}
			else
			{
				$count_exceptions++;
				$results_list[] = [
					'path'			=> dirname($file),
					'hash_file_id'	=> $hash_data[0]['hash_file_id'],
					'hash_line_num'	=> $hash_data[0]['hash_line_num'],
					'msg_type'		=> '- EXCEPTION',
					'file'			=> $file,
					'msg'			=> '',
				];
			}
		}
		continue;
	}

	$count_checked++;
	if (!file_exists($root_path . $file))
	{
		$count_missing++;
		$results_list[] = [
			'path'			=> dirname($file),
			'hash_file_id'	=> $hash_data[0]['hash_file_id'],
			'hash_line_num'	=> $hash_data[0]['hash_line_num'],
			'msg_type'		=> '! MISSING',
			'file'			=> $file,
			'msg'			=> '',
		];
		continue;
	}

	switch ($file)
	{
		case 'config.php':
			$filesize = @filesize($file);
			if ($filesize === 0)
			{
				$count_warning++;
				$results_list[] = [
					'path'			=> dirname($file),
					'hash_file_id'	=> $hash_data[0]['hash_file_id'],
					'hash_line_num'	=> $hash_data[0]['hash_line_num'],
					'msg_type'		=> '! WARNING',
					'file'			=> $file,
					'msg'			=> 'has 0 bytes',
				];
			}
			else if ($filesize === false)
			{
				$count_error++;
				$results_list[] = [
					'path'			=> dirname($file),
					'hash_file_id'	=> $hash_data[0]['hash_file_id'],
					'hash_line_num'	=> $hash_data[0]['hash_line_num'],
					'msg_type'		=> '~ ERROR',
					'file'			=> $file,
					'msg'			=> 'file size could not be determined',
				];
			}
			continue 2;
	}

	$hash_calc = @md5_file($root_path . $file);
	if ($hash_calc !== false)
	{
		if ($hash_data[0]['hash'] != $hash_calc)
		{
			if (($hash_data[1]['hash'] ?? '') == $hash_calc)
			{
				$count_notice++;
				$results_list[] = [
					'path'			=> dirname($file),
					'hash_file_id'	=> $hash_data[1]['hash_file_id'],
					'hash_line_num'	=> $hash_data[1]['hash_line_num'],
					'msg_type'		=> '  NOTICE',
					'file'			=> $file,
					'msg'			=> 'has the ' . $checksums_diff_name . ' hash',
				];
			}
			else
			{
				$count_changed++;
				$results_list[] = [
					'path'			=> dirname($file),
					'hash_file_id'	=> $hash_data[0]['hash_file_id'],
					'hash_line_num'	=> $hash_data[0]['hash_line_num'],
					'msg_type'		=> '* CHANGED',
					'file'			=> $file,
					'msg'			=> '(hash: ' . $hash_calc . ')',
				];
			}
		}
	}
	else
	{
		$count_error++;
		$results_list[] = [
			'path'			=> dirname($file),
			'hash_file_id'	=> $hash_data[0]['hash_file_id'],
			'hash_line_num'	=> $hash_data[0]['hash_line_num'],
			'msg_type'		=> '~ ERROR',
			'file'			=> $file,
			'msg'			=> 'MD5 hash could not be calculated',
		];
	}
}

/*
* Format results and add to display buffer
*/
if (count($results_list) > 0)
{
	uasort($results_list, function($a, $b) {
		return [$a['path'], $a['file']] <=> [$b['path'], $b['file']];
	});

	$line_num_len = column_max_len($results_list, 'hash_line_num');
	$msg_type_len = column_max_len($results_list, 'msg_type');
	foreach ($results_list as $row)
	{
		$output .= sprintf('{%1$u:%2$ ' . $line_num_len . 'u} %3$ -' . $msg_type_len . 's: [%4$s]%5$s',
			/* 1 */ $row['hash_file_id'],
			/* 2 */ $row['hash_line_num'],
			/* 3 */ $row['msg_type'],
			/* 4 */ $row['file'],
			/* 5 */ ($row['msg'] != '' ? ' ' . $row['msg'] : '')
		) . EOL;
	}
}
else
{
	$output = 'no issues found' . EOL ;
}

add_list_lines($output);

/*
* Display: results and summary
*/
$output .= EOL;
if ($debug_mode)
{
	$output .= sprintf('Ignored      : % ' . $checksums_count_len . 'u', $count_ignored) . EOL;
	$output .= sprintf('Exceptions   : % ' . $checksums_count_len . 'u', $count_exceptions) . EOL;
}
$output .= sprintf('Checked files: % ' . $checksums_count_len . 'u', $count_checked) . EOL;
$output .= sprintf('Missing files: % ' . $checksums_count_len . 'u', $count_missing) . EOL;
if ($count_warning || $debug_mode)
{
	$output .= sprintf('Warnings     : % ' . $checksums_count_len . 'u', $count_warning) . EOL;
}
$output .= sprintf('Changed files: % ' . $checksums_count_len . 'u', $count_changed) . EOL;
if ($count_notice || $debug_mode)
{
	$output .= sprintf('Notices      : % ' . $checksums_count_len . 'u', $count_notice) . EOL;
}
if ($count_error || $debug_mode)
{
	$output .= sprintf('Errors       : % ' . $checksums_count_len . 'u', $count_error) . EOL;
}

$output .= EOL;
$output .= sprintf('Finished! Run time: %.3f seconds', microtime(true) - $start_time) . EOL;
$output .= html_end();

flush_buffer();

/*
* Script end
*/

function is_ignored(string $file, array &$ignore_list): bool
{
	foreach ($ignore_list as $ignored)
	{
		$regex = @preg_match($ignored, $file);
		if ($regex !== false && $regex)
		{
			return true;
		}
	}
	return false;
}

function is_exception(string $file, array &$exceptions_list, string $root_path): bool
{
	foreach ($exceptions_list as $exception)
	{
		if (strpos($file, $exception) === 0 && !file_exists($root_path . $exception))
		{
			return true;
		}
	}
	return false;
}

function flush_buffer(): void
{
	global $output;

	echo $output;
	$output = '';
	flush();
}

function terminate(string $message): void
{
	global $output;

	$output .= "ERROR: " . $message . EOL;
	$output .= html_end();
	flush_buffer();
	exit;
}

function html_start(): string
{
	$output = '';
	if (IS_BROWSER)
	{
		$output .= '<!DOCTYPE HTML>' . EOL;
		$output .= '<html>' . EOL;
		$output .= '<head>' . EOL;
		$output .= '	<title>phpBB File Check</title>' . EOL;
		$output .= '	<meta name="robots" content="noindex">' . EOL;
		$output .= '</head>' . EOL;
		$output .= '<body style="font-size: 1.1em;">' . EOL;
		$output .= '<pre>' . EOL;
	}
	return $output;
}

function html_end(): string
{
	$output = '';
	if (IS_BROWSER)
	{
		$output .= '</pre>' . EOL;
		$output .= '</body>' . EOL;
		$output .= '</html>' . EOL;
	}
	return $output;
}

function add_list_lines(string &$text): void
{
	$output_rows = array_map('strlen', explode(EOL, $text));
	$list_separator = str_repeat('-', max($output_rows));
	$text = EOL . $list_separator . EOL . $text . $list_separator . EOL ;
}

function load_checksum_file(string $checksum_file, string &$checksums_name, string &$checksums_ver, int $hash_file_id, array &$hash_list): void
{
	$checksums = @file($checksum_file, FILE_IGNORE_NEW_LINES);
	$checksum_basename = basename($checksum_file);
	if ($checksums === false)
	{
		terminate("checksum file [{$checksum_basename}] could not be read");
	}

	preg_match('/^(.*?):([0-9]+\.[0-9]+\.[0-9]+)/', end($checksums), $matches);
	if (is_array($matches) && count($matches) == 3)
	{
		array_pop($checksums);
		$checksums_name	= $matches[1];
		$checksums_ver	= $matches[2];
	}
	else
	{
		terminate("checksum file [{$checksum_basename}] does not have a valid version");
	}

	$line_num		= 0;
	$error_messages	= '';
	foreach ($checksums as $row)
	{
		$line_num++;
		preg_match('/^([0-9a-f]{32}) \*([' . VALID_CHARS . ']+)$/', $row, $matches);
		if (is_array($matches) && count($matches) == 3)
		{
			$hash = $matches[1];
			$file = $matches[2];

			if (isset($hash_list[$file]))
			{
				$hash_list[$file][] = [
					'hash_file_id'	=> $hash_file_id,
					'hash_line_num'	=> $line_num,
					'hash'			=> $hash,
				];
			}
			else
			{
				$hash_list[$file] = [
					[
						'hash_file_id'	=> $hash_file_id,
						'hash_line_num'	=> $line_num,
						'hash'			=> $hash,
					]
				];
			}
		}
		else
		{
			$error_messages .= "line {$line_num}: [{$row}] invalid row" . EOL;
		}
	}
	if ($error_messages != '')
	{
		add_list_lines($error_messages);
		terminate("checksum file [{$checksum_basename}] has the following issues:" . EOL . $error_messages);
	}
}

function column_max_len(array &$array, string $column_name): int
{
	$column = array_column($array, $column_name);
	return max(array_map('strlen', $column));
}
