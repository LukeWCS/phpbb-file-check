<?php
/**
*
* phpBB File Check - Checks all relevant files of a phpBB installation for existence and integrity
*
* @copyright (c) 2023 LukeWCS <phpBB.de>
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
*/

# phpcs:disable PSR1.Files.SideEffects

/*
* Check requirements
*/
if (!(version_compare(PHP_VERSION, '7.1.0', '>=') && version_compare(PHP_VERSION, '8.4.0-dev', '<')))
{
	echo 'File Check error: Invalid PHP Version ' . PHP_VERSION;
	exit;
}

/*
* Initialization
*/
define('EOL'			, "\n");
define('VALID_CHARS'	, 'a-zA-Z0-9\/\-_.');
define('IS_BROWSER'		, $_SERVER['HTTP_USER_AGENT'] ?? '' != '');
define('ROOT_PATH'		, __DIR__ . '/');

$debug_mode				= 0;

$checksum_file_name		= 'filecheck';
$checksum_file_suffix	= '.md5';
$checksum_file			= $checksum_file_name . $checksum_file_suffix;
$checksum_diff_file		= $checksum_file_name . '_diff' . $checksum_file_suffix;
$ignore_file			= 'filecheck_ignore.txt';
$exceptions_file		= 'filecheck_exceptions.txt';
$constants_file			= 'includes/constants.php';
$checksum_select_mode	= 'Manually';
$empty_file_hash		= 'd41d8cd98f00b204e9800998ecf8427e';

$ver					= '1.3.0';
$title					= "phpBB File Check v{$ver}";
$unknown				= '{unknown}';
$output					= html_start();
$summary				= '';
$exec_info				= '';
$start_time				= microtime(true);

$ignore_list = [
	'^\.git',
	'\/\.git',
];

$exception_list = [
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
if (file_exists(ROOT_PATH . $constants_file))
{
	$constants_content = @file_get_contents(ROOT_PATH . $constants_file);
	if ($constants_content !== false)
	{
		preg_match('/\'PHPBB_VERSION\'\s*,\s*\'([0-9]+\.[0-9]+\.[0-9]+)\'/', $constants_content, $matches);
		$PHPBB_VERSION = $matches[1] ?? null;
		if ($PHPBB_VERSION !== null && !file_exists(ROOT_PATH . $checksum_file))
		{
			$checksum_file			= $checksum_file_name . '_' . $PHPBB_VERSION . $checksum_file_suffix;
			$checksum_diff_file		= $checksum_file_name . '_' . $PHPBB_VERSION . '_diff' . $checksum_file_suffix;
			$checksum_select_mode	= 'Auto';
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
if (file_exists(ROOT_PATH . $checksum_file))
{
	load_checksum_file(ROOT_PATH . $checksum_file, $checksums_name, $checksums_ver, 1, $hash_list);
}
else
{
	$output .= $constants_notice ?? '';
	terminate("checksum file [{$checksum_file}] not found");
}

if (file_exists(ROOT_PATH . $checksum_diff_file))
{
	load_checksum_file(ROOT_PATH . $checksum_diff_file, $checksums_diff_name, $checksums_diff_ver, 2, $hash_list);
}

$count_checksums		= count($hash_list);
$checksums_count_len	= strlen($count_checksums);

/*
* Load and check the external ignore list
*/
if (file_exists(ROOT_PATH . $ignore_file))
{
	$import_list	= @file(ROOT_PATH . $ignore_file, FILE_IGNORE_NEW_LINES);
	$line_num		= 0;
	$error_messages	= '';
	if ($import_list !== false)
	{
		foreach ($import_list as $row)
		{
			$line_num++;
			if (@preg_match('/' . $row . '/', '') === false)
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
$ignore_list_regex = implode('|', $ignore_list);

/*
* Load and check the external exception list
*/
if (file_exists(ROOT_PATH . $exceptions_file))
{
	$import_list	= @file(ROOT_PATH . $exceptions_file, FILE_IGNORE_NEW_LINES);
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
		$exception_list = array_merge($exception_list, $import_list);
		sort($exception_list);
	}
	else
	{
		terminate("exception list [{$exceptions_file}] could not be read");
	}
}

/*
* Display: versions and number of checksums
*/
$output .= sprintf('Version mode : %1$s', $checksum_select_mode) . EOL;
$output .= sprintf('phpBB Version: %1$s', $PHPBB_VERSION ?? $unknown) . EOL;
$output .= sprintf('MD5 Version 1: %1$s (%2$s)', $checksums_ver, $checksums_name) . EOL;
if (file_exists(ROOT_PATH . $checksum_diff_file))
{
	$output .= sprintf('MD5 Version 2: %1$s (%2$s)', $checksums_diff_ver, $checksums_diff_name) . EOL;
}
$output .= sprintf('PHP Version  : %1$s (%2$s)', PHP_VERSION, PHP_OS) . EOL;
$output .= EOL;
$output .= 'Please wait, ' . $count_checksums . ' checksums are being processed...' . EOL;

if (IS_BROWSER)
{
	if (session_start() && ($_SESSION['exec_check'] ?? 0) !== 1)
	{
		$_SESSION['exec_check'] = 1;
		header('refresh: 0; url=filecheck.php');
		$output .= html_end(false);
		flush_buffer();
		exit;
	}
	$_SESSION = [];
}
else
{
	flush_buffer();
}

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
$result_list		= [];
$result_struct		= function (string &$file, array &$hash_data, string $msg_type, string $msg, int &$counter): array
{
	$counter++;
	return [
		'file'			=> $file,
		'path'			=> dirname($file),
		'hash_file_id'	=> $hash_data['hash_file_id'],
		'hash_line_num'	=> $hash_data['hash_line_num'],
		'msg_type'		=> $msg_type,
		'msg'			=> $msg,
	];
};
foreach ($hash_list as $file => $hash_data)
{
	$is_ignored = is_ignored($file, $ignore_list_regex);
	if ($is_ignored || is_exception($file, $exception_list))
	{
		if ($debug_mode)
		{
			if ($is_ignored)
			{
				$result_list[] = $result_struct($file, $hash_data[0], '- IGNORED', '', $count_ignored);
			}
			else
			{
				$result_list[] = $result_struct($file, $hash_data[0], '- EXCEPTION', '', $count_exceptions);
			}
		}
		continue;
	}

	$count_checked++;
	if (!file_exists(ROOT_PATH . $file))
	{
		$result_list[] = $result_struct($file, $hash_data[0], '! MISSING', '', $count_missing);
		continue;
	}

	$calc_hash = @md5_file(ROOT_PATH . $file);
	if ($calc_hash !== false)
	{
		if ($file == 'config.php')
		{
			if ($calc_hash == $empty_file_hash)
			{
				$result_list[] = $result_struct($file, $hash_data[0], '! WARNING', 'has 0 bytes', $count_warning);
			}
		}
		else if ($hash_data[0]['hash'] != $calc_hash)
		{
			if (($hash_data[1]['hash'] ?? '') == $calc_hash)
			{
				$result_list[] = $result_struct($file, $hash_data[1], '  NOTICE', 'has the ' . $checksums_diff_name . ' hash', $count_notice);
			}
			else if ($calc_hash == $empty_file_hash)
			{
				$result_list[] = $result_struct($file, $hash_data[0], '! WARNING', 'has 0 bytes', $count_warning);
			}
			else
			{
				$result_list[] = $result_struct($file, $hash_data[0], '* CHANGED', '(hash: ' . $calc_hash . ')', $count_changed);
			}
		}
	}
	else
	{
		$result_list[] = $result_struct($file, $hash_data[0], '~ ERROR', 'MD5 hash could not be calculated', $count_error);
	}
}

if (IS_BROWSER)
{
	flush_buffer();
}

/*
* Format results and add to display buffer
*/
if (count($result_list) > 0)
{
	uasort($result_list, function($a, $b) {
		return [$a['path'], $a['file']] <=> [$b['path'], $b['file']];
	});

	$line_num_len = column_max_len($result_list, 'hash_line_num');
	$msg_type_len = column_max_len($result_list, 'msg_type');
	foreach ($result_list as $row)
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
if ($debug_mode)
{
	$summary .= sprintf('Ignored      : % ' . $checksums_count_len . 'u', $count_ignored) . EOL;
	$summary .= sprintf('Exceptions   : % ' . $checksums_count_len . 'u', $count_exceptions) . EOL;
}
$summary .= sprintf('Checked files: % ' . $checksums_count_len . 'u', $count_checked) . EOL;
$summary .= sprintf('Missing files: % ' . $checksums_count_len . 'u', $count_missing) . EOL;
if ($count_warning || $debug_mode)
{
	$summary .= sprintf('Warnings     : % ' . $checksums_count_len . 'u', $count_warning) . EOL;
}
$summary .= sprintf('Changed files: % ' . $checksums_count_len . 'u', $count_changed) . EOL;
if ($count_notice || $debug_mode)
{
	$summary .= sprintf('Notices      : % ' . $checksums_count_len . 'u', $count_notice) . EOL;
}
if ($count_error || $debug_mode)
{
	$summary .= sprintf('FC Errors    : % ' . $checksums_count_len . 'u', $count_error) . EOL;
}

$exec_info .= sprintf('Run time          : %.3f seconds', microtime(true) - $start_time) . EOL;
$exec_info .= sprintf('Max execution time: %u seconds', ini_get('max_execution_time')) . EOL;
$exec_info .= sprintf('Memory peak usage : %s bytes', number_format(memory_get_peak_usage())) . EOL;
$exec_info .= sprintf('Memory limit      : %s',  ini_get('memory_limit')) . EOL;

$output .= EOL;
$output .= 'Finished!' . EOL;
$output .= EOL;
$output .= 'Report summary' . EOL;
$output .= str_repeat('-', column_max_len($summary)) . EOL;
$output .= $summary;
$output .= EOL;
$output .= 'Script/PHP Information' . EOL;
$output .= str_repeat('-', column_max_len($exec_info)) . EOL;
$output .= $exec_info;
$output .= html_end();

flush_buffer();

/*
* Script end
*/

function is_ignored(string &$file, &$ignore_list_regex = ''): bool
{
	if (preg_match('/' . $ignore_list_regex . '/', $file) === 1)
	{
		return true;
	}
	return false;
}

function is_exception(string &$file, array &$exception_list): bool
{
	foreach ($exception_list as $exception)
	{
		if (strpos($file, $exception) === 0 && !file_exists(ROOT_PATH . $exception))
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
}

function terminate(string $message): void
{
	global $output;

	$output .= "File Check error: " . $message . EOL;
	$output .= html_end();
	flush_buffer();
	exit;
}

function html_start(): string
{
	if (!IS_BROWSER)
	{
		return '';
	}

	$output = <<<'_HTML_'
<!DOCTYPE HTML>
<html>
<head>
	<title>phpBB File Check</title>
	<meta name="robots" content="noindex">
	<style>
		body {
			font-size: 1.1em;
		}
		.prevent-select {
			-webkit-user-select: none;
			-ms-user-select: none;
			user-select: none;
		}
		#result-message {
			font-family: verdana;
			font-size: 0.8em;
		}
		.msg-success:before {
			color: limegreen;
			content: "\2714\0020";
		}
		.msg-error:before {
			color: red;
			content: "\2716\0020";
		}
		.bbcode {
			opacity: 0.3;
		}
	</style>
</head>
<body>
<pre>
<span class="bbcode">[code]</span>

_HTML_;
	return $output;
}

function html_end(bool $show_button = true): string
{
	if (!IS_BROWSER)
	{
		return '';
	}

	$output = <<<'_HTML_'
<span class="bbcode">[/code]</span>
</pre>

_HTML_;
	if ($show_button)
	{
		$output .= <<<'_HTML_'
<hr>
<div class="prevent-select">
	<button>Copy report to clipboard</button> <span id="result-message" hidden>message</span>
</div>
<script>
	function copyReport()
	{
		const button = this;
		const message = document.querySelector('#result-message');

		button.disabled = true;
		if (navigator.clipboard) {
			try {
				navigator.clipboard.writeText(document.querySelector('pre').innerText);
				message.className = 'msg-success';
				message.innerHTML = 'copy successful';
			} catch (err) {
				console.error(err);
				message.className = 'msg-error';
				message.innerHTML = 'copy failed!';
			}
		} else {
			const selection = window.getSelection();
			const range = document.createRange();

			range.selectNode(document.querySelector('pre'));
			selection.removeAllRanges();
			selection.addRange(range);
			try {
				document.execCommand('copy');
				message.className = 'msg-success';
				message.innerHTML = 'copy successful';
			} catch (err) {
				console.error(err);
				message.className = 'msg-error';
				message.innerHTML = 'copy failed!';
			}
			selection.removeAllRanges();
		}
		message.hidden = false;
		setTimeout(function() {
			message.hidden = (message.className == 'msg-success');
			button.disabled = false;
		}, 3000);
	}
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelector('button').addEventListener('click', copyReport);
	});
</script>

_HTML_;
	}
	$output .= <<<'_HTML_'
</body>
</html>

_HTML_;
	return $output;
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
			$hash			= $matches[1];
			$file			= $matches[2];
			$hash_struct	= [
				'hash_file_id'	=> $hash_file_id,
				'hash_line_num'	=> $line_num,
				'hash'			=> $hash,
			];

			if (isset($hash_list[$file]))
			{
				$hash_list[$file][] = $hash_struct;
			}
			else
			{
				$hash_list[$file] = [$hash_struct];
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

function add_list_lines(string &$list): void
{
	$list_separator = str_repeat('-', column_max_len($list));
	$list = EOL . $list_separator . EOL . $list . $list_separator . EOL ;
}

function column_max_len($list, string $column_name = ''): int
{
	$column = is_array($list) ? array_column($list, $column_name) : explode(EOL, $list);
	return max(array_map('strlen', $column));
}
