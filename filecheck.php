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

$debug_mode				= false;

$root_path				= __DIR__ . '/';
$checksum_file_name		= 'filecheck';
$checksum_file_suffix	= '.md5';
$checksum_file			= $checksum_file_name . $checksum_file_suffix;
$checksum_diff_file		= $checksum_file_name . '_diff' . $checksum_file_suffix;
$checksums_name			= '';
$checksums_ver			= '';
$checksums_diff_name	= '';
$checksums_diff_ver		= '';
$checksum_select_mode	= 'MANUALLY';
$ignore_file			= 'filecheck_ignore.txt';
$exceptions_file		= 'filecheck_exceptions.txt';
$contants_file			= 'includes/constants.php';

$ver					= '1.1.0';
$title					= "phpBB File Check v{$ver}";
$is_browser				= $_SERVER['HTTP_USER_AGENT'] ?? '' != '';
$lf						= "\n";
$unknown				= '{unknown}';
$output					= report_start($is_browser);
$hash_table				= [];
$results_table			= [];
$start_time				= microtime(true);

$ignored = [
	'/^\.git|\/\.git/',
];
$exceptions = [
	'docs/',
	'ext/phpbb/viglink/',
	'install/',
];

$output .= $title . ($debug_mode ? ' (DEBUG MODE)' : '') . $lf;
$output .= str_repeat('=', strlen($title)) . $lf . $lf;

if (file_exists($root_path . $contants_file))
{
	preg_match('/\'PHPBB_VERSION\'.*?\'([0-9]+?\.[0-9]+?\.[0-9]+?)\'/', file_get_contents($root_path . $contants_file), $matches);
	$PHPBB_VERSION = $matches[1] ?? null;
	if ($PHPBB_VERSION !== null && !file_exists($root_path . $checksum_file))
	{
		$checksum_file			= $checksum_file_name . '_' . $PHPBB_VERSION . $checksum_file_suffix;
		$checksum_diff_file		= $checksum_file_name . '_' . $PHPBB_VERSION . '_diff' . $checksum_file_suffix;
		$checksum_select_mode	= 'AUTO';
	}
}

if (file_exists($root_path . $checksum_file))
{
	load_checksum_file($root_path . $checksum_file, $checksums_name, $checksums_ver, 1);
}
else
{
	terminate("checksum file [{$checksum_file}] not found");
}

if (file_exists($root_path . $checksum_diff_file))
{
	load_checksum_file($root_path . $checksum_diff_file, $checksums_diff_name, $checksums_diff_ver, 2);
}

if (file_exists($root_path . $ignore_file))
{
	$file			= @file($root_path . $ignore_file, FILE_IGNORE_NEW_LINES);
	$line_num		= 0;
	$regex_errors	= '';
	if ($file !== false)
	{
		foreach ($file as $row)
		{
			$line_num++;
			if (@preg_match($row, '') === false)
			{
				$regex_errors = sprintf('line %1$u: invalid RegEx "%2$s"',
					/* 1 */ $line_num,
					/* 2 */ $row
				) . $lf;
			}
		}
		if ($regex_errors != '')
		{
			add_list_lines($regex_errors);
			terminate("ignore list [{$ignore_file}] has the following errors:" . $lf. $regex_errors);
		}
		$ignored = array_merge($ignored, $file);
	}
	else
	{
		terminate("ignore list [{$ignore_file}] could not be read");
	}
}

if (file_exists($root_path . $exceptions_file))
{
	$file = @file($root_path . $exceptions_file, FILE_IGNORE_NEW_LINES);
	if ($file !== false)
	{
		$exceptions = array_merge($exceptions, $file);
	}
	else
	{
		terminate("exception list [{$exceptions_file}] could not be read");
	}
}

$count_checksums	= count($hash_table);
$counter_len		= strlen($count_checksums);

$output .= sprintf('phpBB Version: %1$s', $PHPBB_VERSION ?? $unknown) . $lf;
$output .= sprintf('MD5 Version 1: %1$s (%2$s) %3$s', $checksums_ver, $checksums_name, $checksum_select_mode) . $lf;
if (file_exists($root_path . $checksum_diff_file))
{
	$output .= sprintf('MD5 Version 2: %1$s (%2$s) %3$s', $checksums_diff_ver, $checksums_diff_name, $checksum_select_mode) . $lf;
}
$output .= sprintf('PHP Version  : %1$s (%2$s)', PHP_VERSION, PHP_OS) . $lf;

$output .= $lf;
$output .= 'Please wait, ' . $count_checksums . ' checksums are being processed...' . $lf;

flush_buffer();

$count_missing		= 0;
$count_changed		= 0;
$count_error		= 0;
$count_warning		= 0;
$count_notice		= 0;
$count_ignored		= 0;
$count_exceptions	= 0;
$count_excluded		= 0;
$count_checked		= 0;
$index_results		= -1;
foreach ($hash_table as $file => $data)
{
	$is_ignored = is_ignored($file, $ignored);
	if ($is_ignored || is_exception($file, $exceptions))
	{
		$count_excluded++;
		if ($debug_mode)
		{
			if ($is_ignored)
			{
				$count_ignored++;
				$index_results++;
				$results_table[$index_results] = [
					'path'			=> dirname($file),
					'hash_file_id'	=> $hash_table[$file]['hash_file_id'],
					'hash_line_num'	=> $hash_table[$file]['hashes'][0]['line_num'],
					'msg_type'		=> '- IGNORED',
					'file'			=> $file,
					'msg'			=> '',
				];
			}
			else
			{
				$count_exceptions++;
				$index_results++;
				$results_table[$index_results] = [
					'path'			=> dirname($file),
					'hash_file_id'	=> $hash_table[$file]['hash_file_id'],
					'hash_line_num'	=> $hash_table[$file]['hashes'][0]['line_num'],
					'msg_type'		=> '- EXCEPTION',
					'file'			=> $file,
					'msg'			=> '',
				];
			}
		}
		continue;
	}

	$count_checked++;
	if (file_exists($root_path . $file))
	{
		switch ($file)
		{
			case 'config.php':
				$filesize = @filesize($file);
				if ($filesize === 0)
				{
					$count_warning++;
					$index_results++;
					$results_table[$index_results] = [
						'path'			=> dirname($file),
						'hash_file_id'	=> $hash_table[$file]['hash_file_id'],
						'hash_line_num'	=> $hash_table[$file]['hashes'][0]['line_num'],
						'msg_type'		=> '! WARNING',
						'file'			=> $file,
						'msg'			=> 'has 0 bytes',
					];
				}
				else if ($filesize === false)
				{
					$count_error++;
					$index_results++;
					$results_table[$index_results] = [
						'path'			=> dirname($file),
						'hash_file_id'	=> $hash_table[$file]['hash_file_id'],
						'hash_line_num'	=> $hash_table[$file]['hashes'][0]['line_num'],
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
			if ($hash_table[$file]['hashes'][0]['hash'] != $hash_calc)
			{
				if (($hash_table[$file]['hashes'][1]['hash'] ?? '') == $hash_calc)
				{
					$count_notice++;
					$index_results++;
					$results_table[$index_results] = [
						'path'			=> dirname($file),
						'hash_file_id'	=> 2,
						'hash_line_num'	=> $hash_table[$file]['hashes'][1]['line_num'],
						'msg_type'		=> '  NOTICE',
						'file'			=> $file,
						'msg'			=> 'has the ' . $checksums_diff_name . ' hash',
					];
				}
				else
				{
					$count_changed++;
					$index_results++;
					$results_table[$index_results] = [
						'path'			=> dirname($file),
						'hash_file_id'	=> $hash_table[$file]['hash_file_id'],
						'hash_line_num'	=> $hash_table[$file]['hashes'][0]['line_num'],
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
			$index_results++;
			$results_table[$index_results] = [
				'path'			=> dirname($file),
				'hash_file_id'	=> $hash_table[$file]['hash_file_id'],
				'hash_line_num'	=> $hash_table[$file]['hashes'][0]['line_num'],
				'msg_type'		=> '~ ERROR',
				'file'			=> $file,
				'msg'			=> 'MD5 hash could not be calculated',
			];
		}
	}
	else
	{
		$count_missing++;
		$index_results++;
		$results_table[$index_results] = [
			'path'			=> dirname($file),
			'hash_file_id'	=> $hash_table[$file]['hash_file_id'],
			'hash_line_num'	=> $hash_table[$file]['hashes'][0]['line_num'],
			'msg_type'		=> '! MISSING',
			'file'			=> $file,
			'msg'			=> '',
		];
	}
}

if (count($results_table) > 0)
{
	uasort($results_table, function($a, $b) {
		return [$a['path'], $a['file']] <=> [$b['path'], $b['file']];
	});

	$column_msg_type = array_column($results_table, 'msg_type');
	$msg_type_len = max(array_map('strlen', $column_msg_type));
	foreach ($results_table as $row)
	{
		$output .= sprintf('{%1$u:%2$ ' . $counter_len . 'u} %3$ -' . $msg_type_len . 's: [%4$s]%5$s',
			/* 1 */ $row['hash_file_id'],
			/* 2 */ $row['hash_line_num'],
			/* 3 */ $row['msg_type'],
			/* 4 */ $row['file'],
			/* 5 */ ($row['msg'] != '' ? ' ' . $row['msg'] : '')
		) . $lf;
	}
}
else
{
	$output = 'no issues found' . $lf ;
}

add_list_lines($output);

$output .= $lf;
if ($debug_mode)
{
	$output .= sprintf('Ignored      : % ' . $counter_len . 'u', $count_ignored) . $lf;
	$output .= sprintf('Exceptions   : % ' . $counter_len . 'u', $count_exceptions) . $lf;
}
$output .= sprintf('Checked files: % ' . $counter_len . 'u', $count_checked) . $lf;
$output .= sprintf('Missing files: % ' . $counter_len . 'u', $count_missing) . $lf;
if ($count_warning || $debug_mode)
{
	$output .= sprintf('Warnings     : % ' . $counter_len . 'u', $count_warning) . $lf;
}
$output .= sprintf('Changed files: % ' . $counter_len . 'u', $count_changed) . $lf;
if ($count_notice || $debug_mode)
{
	$output .= sprintf('Notices      : % ' . $counter_len . 'u', $count_notice) . $lf;
}
if ($count_error || $debug_mode)
{
	$output .= sprintf('Errors       : % ' . $counter_len . 'u', $count_error) . $lf;
}

$output .= $lf;
$output .= sprintf('Finished! Run time: %.3f seconds', microtime(true) - $start_time) . $lf;
$output .= report_end($is_browser);

flush_buffer();

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

function is_exception(string $file, array &$exception_list): bool
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

function flush_buffer(): void
{
	global $output;

	echo $output;
	$output = '';
	flush();
}

function report_start(bool $is_browser = false): string
{
	global $lf;

	$output = '';
	if ($is_browser)
	{
		$output .= '<!DOCTYPE HTML>' . $lf;
		$output .= '<html>' . $lf;
		$output .= '<head>' . $lf;
		$output .= '	<title>phpBB File Check</title>' . $lf;
		$output .= '	<meta name="robots" content="noindex">' . $lf;
		$output .= '</head>' . $lf;
		$output .= '<body style="font-size: 1.1em;">' . $lf;
		$output .= '<pre>' . $lf;
	}
	return $output;
}

function report_end(bool $is_browser = false): string
{
	global $lf;

	$output = '';
	if ($is_browser)
	{
		$output .= '</pre>' . $lf;
		$output .= '</body>' . $lf;
		$output .= '</html>' . $lf;
	}
	return $output;
}

function terminate(string $message): void
{
	global $lf;
	global $output;

	$output .= "ERROR: " . $message . $lf;
	$output .= report_end();
	flush_buffer();
	exit;
}

function add_list_lines(string &$text): void
{
	global $lf;

	$output_rows = array_map('strlen', explode($lf, $text));
	$list_separator = str_repeat('-', max($output_rows));
	$text = $lf . $list_separator . $lf . $text . $list_separator . $lf ;
}

function load_checksum_file(string $checksum_file, string &$checksums_name, string &$checksums_ver, int $hash_file_id): void
{
	global $lf;
	global $hash_table;

	$checksums = @file($checksum_file, FILE_IGNORE_NEW_LINES);
	$md5_errors = '';
	if ($checksums !== false)
	{
		preg_match('/^(.*?)\s*?:\s*?([0-9]+?\.[0-9]+?\.[0-9]+)/', end($checksums), $matches);
		if (is_array($matches) && count($matches) == 3)
		{
			array_pop($checksums);
			$checksums_name	= $matches[1];
			$checksums_ver	= $matches[2];
		}
		else
		{
			terminate("checksum file [{$checksum_file}] does not have a valid version");
		}

		$line_num = 0;
		foreach ($checksums as $row)
		{
			$line_num++;
			preg_match('/^([0-9a-f]{32}) [* ]([^*]+?)$/', $row, $matches);
			if (is_array($matches) && count($matches) == 3)
			{
				$hash = $matches[1];
				$file = $matches[2];

				if (isset($hash_table[$file]))
				{
					$hash_table[$file]['hashes'] += [
						1	=> [
							'line_num'	=> $line_num,
							'hash'		=> $hash,
						]
					];
				}
				else
				{
					$hash_table += [
						$file	=> [
							'hash_file_id'	=> $hash_file_id,
							'hashes'		=> [
								0	=> [
									'line_num'	=> $line_num,
									'hash'		=> $hash,
								],
							]
						]
					];
				}
			}
			else
			{
				$md5_errors .= sprintf('line %1$u: invalid hash row: "%2$s"',
					/* 1 */ $line_num,
					/* 2 */ $row
				) . $lf;
			}
		}
		if ($md5_errors != '')
		{
			add_list_lines($md5_errors);
			terminate("checksum file [{$checksum_file}] has the following errors:" . $lf. $md5_errors);
		}
	}
	else
	{
		terminate("checksum file [{$checksum_file}] could not be read");
	}
}
