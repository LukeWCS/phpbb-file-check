<?php
/**
*
* phpBB File Check - Checks all relevant files of a phpBB installation for existence and integrity
*
* @copyright (c) 2023 LukeWCS <phpBB.de>
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
* PHP requirements: 7.1.0 - 8.3.x
*
*/

# phpcs:disable PSR1.Files.SideEffects
# phpcs:set VariableAnalysis.CodeAnalysis.VariableAnalysis validUnusedVariableNames notices

/*
* Initialization
*/
define('EOL'			, "\n");
define('VALID_CHARS'	, 'a-zA-Z0-9\/\-_.');
define('IS_BROWSER'		, $_SERVER['HTTP_USER_AGENT'] ?? '' != '');
define('ROOT_PATH'		, __DIR__ . '/');

$checksum_file_name		= 'filecheck';
$checksum_file_suffix	= '.md5';
$checksum_file			= $checksum_file_name . $checksum_file_suffix;
$checksum_diff_file		= $checksum_file_name . '_diff' . $checksum_file_suffix;
$ignore_file			= 'filecheck_ignore.txt';
$exceptions_file		= 'filecheck_exceptions.txt';
$config_file			= 'filecheck_config.php';
$constants_file			= 'includes/constants.php';
$checksum_version_mode	= 'Manually';
$checksum_file_flags	= [];
$config					= [];
$empty_file_hash		= 'd41d8cd98f00b204e9800998ecf8427e';

$ver					= '1.4.0';
$title					= "phpBB File Check v{$ver}";
$output					= html_start();
$notices				= '';
$start_time				= microtime(true);

$service = [
	'ZipArchive'		=> class_exists('ZipArchive'),
	'allow_url_fopen'	=> ini_get('allow_url_fopen'),
];

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
$output .= $title . EOL;
$output .= str_repeat('=', strlen($title)) . EOL;

/*
* Include config
*/
if (file_exists(ROOT_PATH . $config_file))
{
	include ROOT_PATH . $config_file;
}
else
{
	notice("Config file [{$config_file}] does not exist.");
}
$config['debug_mode']		= $config['debug_mode']			?? 0;
$config['zip_url_pattern']	= $config['zip_url_pattern']	?? '';
$config['zip_name_pattern']	= $config['zip_name_pattern']	?? '';

$output .= ($config['debug_mode'] ? '(DEBUG MODE)' . EOL . EOL : EOL);

/*
* Get the phpBB version from constants.php
*/
if (file_exists(ROOT_PATH . $constants_file))
{
	$constants_content = @file_get_contents(ROOT_PATH . $constants_file);
	if ($constants_content !== false)
	{
		preg_match('/\'PHPBB_VERSION\'\s*,\s*\'([0-9]+\.[0-9]+\.[0-9]+)(.*?)\'/', $constants_content, $matches);
		$PHPBB_VERSION = $matches[1] ?? null;
		if ($PHPBB_VERSION !== null && !file_exists(ROOT_PATH . $checksum_file))
		{
			$checksum_file			= $checksum_file_name . '_' . $PHPBB_VERSION . $checksum_file_suffix;
			$checksum_diff_file		= $checksum_file_name . '_' . $PHPBB_VERSION . '_diff' . $checksum_file_suffix;
			$checksum_version_mode	= 'Auto';
		}
		if ($PHPBB_VERSION === null)
		{
			notice("phpBB version could not be determined from [{$constants_file}].");
		}
		else if (($matches[2] ?? '') != '')
		{
			terminate("Pre-releases of phpBB are not supported, {$matches[1]}{$matches[2]} found.");
		}
		else
		{
			$PHPBB_VERSION_SEGMENTS = explode('.', $PHPBB_VERSION);
		}
	}
	else
	{
		notice("phpBB file [{$constants_file}] could not be read.");
	}
}
else
{
	notice("phpBB file [{$constants_file}] not found.");
}
$checksum_source = isset($PHPBB_VERSION) && !file_exists(ROOT_PATH . $checksum_file) ? 'ZIP' : 'Folder';

/*
* Automatic download of the appropriate checksum package
*/
if ($checksum_source == 'ZIP')
{
	$zip_url	= str_replace(['{major}', '{minor}', '{patchlevel}'], $PHPBB_VERSION_SEGMENTS, $config['zip_url_pattern']);
	$zip_name	= str_replace(['{major}', '{minor}', '{patchlevel}'], $PHPBB_VERSION_SEGMENTS, $config['zip_name_pattern']);

	if (!file_exists(ROOT_PATH . $zip_name) && $service['ZipArchive'] && $service['allow_url_fopen'] && $config['zip_url_pattern'] != '')
	{
		$ZIP_content = @file_get_contents($zip_url . $zip_name);

		if ($ZIP_content === false)
		{
			terminate("Checksum archive [{$zip_name}] could not be downloaded.");
		}
		if (file_put_contents(ROOT_PATH . $zip_name, $ZIP_content) === false)
		{
			terminate("Checksum archive [{$zip_name}] could not be saved.");
		}
	}

	if (file_exists(ROOT_PATH . $zip_name) && $service['ZipArchive'] && $config['zip_name_pattern'] != '')
	{
		$zip = new ZipArchive;
		if ($zip->open(ROOT_PATH . $zip_name) !== true)
		{
			terminate("Checksum archive [{$zip_name}] could not be opened.");
		}
	}

	notice('zip_url_pattern not set',			$config['zip_url_pattern'] == '');
	notice('zip_name_pattern not set',			$config['zip_name_pattern'] == '');
	notice('ZipArchive class not available',	!$service['ZipArchive']);
	notice('allow_url_fopen not enabled',		!$service['allow_url_fopen']);
}

/*
* Load the checksum files into the hash list
*/
$checksum_name		= '';
$checksum_ver		= '';
$checksum_diff_name	= '';
$checksum_diff_ver	= '';
$hash_list			= [];
if ($checksum_source == 'Folder' && file_exists(ROOT_PATH . $checksum_file)
	|| $checksum_source == 'ZIP' && zip_file_exists($checksum_file)
)
{
	load_checksum_file($checksum_file, $checksum_name, $checksum_ver, $checksum_source, 1, $hash_list);

	if (isset($PHPBB_VERSION) && $checksum_ver != $PHPBB_VERSION)
	{
		terminate("Checksum file [{$checksum_file}] has the wrong version {$checksum_ver}.");
	}
	$checksum_file_flags[] = '1';
}
else
{
	terminate("Checksum file [{$checksum_file}] not found.");
}

if ($checksum_source == 'Folder' && file_exists(ROOT_PATH . $checksum_diff_file)
	|| $checksum_source == 'ZIP' && zip_file_exists($checksum_diff_file)
)
{
	load_checksum_file($checksum_diff_file, $checksum_diff_name, $checksum_diff_ver, $checksum_source, 2, $hash_list);

	if (isset($PHPBB_VERSION) && $checksum_diff_ver != $PHPBB_VERSION)
	{
		terminate("Checksum file [{$checksum_diff_file}] has the wrong version {$checksum_diff_ver}.");
	}
	$checksum_file_flags[] = '2';
}

$count_checksums		= count($hash_list);
$checksums_count_len	= strlen($count_checksums);

/*
* Load and check the external ignore list
*/
if ($checksum_source == 'Folder' && file_exists(ROOT_PATH . $ignore_file)
	|| $checksum_source == 'ZIP' && zip_file_exists($ignore_file)
)
{
	if ($checksum_source == 'Folder')
	{
		$import_list = @file(ROOT_PATH . $ignore_file, FILE_IGNORE_NEW_LINES);
	}
	else
	{
		$import_list = zip_extract_to_array($ignore_file) ?? false;
	}

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
			terminate("Ignore list [{$ignore_file}] has the following issues:" . EOL . $error_messages);
		}
		$ignore_list = array_merge($ignore_list, $import_list);
	}
	else
	{
		terminate("Ignore list [{$ignore_file}] could not be read.");
	}
	$checksum_file_flags[] = 'I';
}
$ignore_list_regex = implode('|', $ignore_list);

/*
* Load and check the external exception list
*/
if ($checksum_source == 'Folder' && file_exists(ROOT_PATH . $exceptions_file)
	|| $checksum_source == 'ZIP' && zip_file_exists($exceptions_file)
)
{
	if ($checksum_source == 'Folder')
	{
		$import_list = @file(ROOT_PATH . $exceptions_file, FILE_IGNORE_NEW_LINES);
	}
	else
	{
		$import_list = zip_extract_to_array($exceptions_file) ?? false;
	}

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
			terminate("Exception list [{$exceptions_file}] has the following issues:" . EOL . $error_messages);
		}
		$exception_list = array_merge($exception_list, $import_list);
		sort($exception_list);
	}
	else
	{
		terminate("Exception list [{$exceptions_file}] could not be read.");
	}
	$checksum_file_flags[] = 'E';
}

if (isset($zip))
{
	$zip->close();
}

/*
* Display: versions and number of checksums
*/
$output .= sprintf('Version mode : %1$s', $checksum_version_mode) . EOL;
$output .= sprintf('MD5 source   : %1$s (%2$s)', $checksum_source, implode(', ', $checksum_file_flags)) . EOL;
$output .= sprintf('phpBB Version: %1$s', $PHPBB_VERSION ?? 'unknown') . EOL;
$output .= sprintf('MD5 Version 1: %1$s (%2$s)', $checksum_ver, $checksum_name) . EOL;
$output .= sprintf('MD5 Version 2: ' . ($checksum_diff_ver ? '%1$s (%2$s)' : '-'), $checksum_diff_ver, $checksum_diff_name) . EOL;
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
		if ($config['debug_mode'])
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
				$result_list[] = $result_struct($file, $hash_data[1], '  NOTICE', 'has the ' . $checksum_diff_name . ' hash', $count_notice);
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
$summary = '';
if ($config['debug_mode'])
{
	$summary .=	sprintf('Ignored      : % ' . $checksums_count_len . 'u', $count_ignored) . EOL;
	$summary .=	sprintf('Exceptions   : % ' . $checksums_count_len . 'u', $count_exceptions) . EOL;
}
$summary .=		sprintf('Checked files: % ' . $checksums_count_len . 'u', $count_checked) . EOL;
$summary .=		sprintf('Missing files: % ' . $checksums_count_len . 'u', $count_missing) . EOL;
if ($count_warning || $config['debug_mode'])
{
	$summary .=	sprintf('Warnings     : % ' . $checksums_count_len . 'u', $count_warning) . EOL;
}
$summary .=		sprintf('Changed files: % ' . $checksums_count_len . 'u', $count_changed) . EOL;
if ($count_notice || $config['debug_mode'])
{
	$summary .=	sprintf('Notices      : % ' . $checksums_count_len . 'u', $count_notice) . EOL;
}
if ($count_error || $config['debug_mode'])
{
	$summary .=	sprintf('FC Errors    : % ' . $checksums_count_len . 'u', $count_error) . EOL;
}

$exec_info =	sprintf('Run time          : %.3f seconds', microtime(true) - $start_time) . EOL;
$exec_info .=	sprintf('Max execution time: %u seconds', ini_get('max_execution_time')) . EOL;
$exec_info .=	sprintf('Memory peak usage : %s bytes', number_format(memory_get_peak_usage())) . EOL;
$exec_info .=	sprintf('Memory limit      : %s',  ini_get('memory_limit')) . EOL;

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

function is_ignored(string &$file, string &$ignore_list_regex): bool
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

function notice(string $message, bool $condition = true): void
{
	if ($condition)
	{
		global $notices;

		$notices .= 'FC_NOTICE: ' . $message . EOL;
	}
}

function terminate(string $message): void
{
	global $output;
	global $notices;

	$output .= $notices;
	$output .= 'FC_ERROR: ' . $message . EOL;
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

function load_checksum_file(string $checksum_file, string &$checksum_name, string &$checksum_ver, string $checksum_source, int $hash_file_id, array &$hash_list): void
{
	if ($checksum_source == 'Folder')
	{
		$checksums = @file(ROOT_PATH . $checksum_file, FILE_IGNORE_NEW_LINES);
	}
	else
	{
		$checksums = zip_extract_to_array($checksum_file) ?? false;
	}

	if ($checksums === false)
	{
		terminate("Checksum file [{$checksum_file}] could not be read.");
	}

	preg_match('/^(.*?):([0-9]+\.[0-9]+\.[0-9]+)/', end($checksums), $matches);
	if (is_array($matches) && count($matches) == 3)
	{
		array_pop($checksums);
		$checksum_name	= $matches[1];
		$checksum_ver	= $matches[2];
	}
	else
	{
		terminate("Checksum file [{$checksum_file}] does not have a valid version.");
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
		terminate("Checksum file [{$checksum_file}] has the following issues:" . EOL . $error_messages);
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

function zip_file_exists(string $file_name): bool
{
	global $zip;

	return isset($zip) && $zip->locateName($file_name) !== false;
}

function zip_extract_to_array(string $file_name): ?array
{
	global $zip;

	if (!isset($zip))
	{
		return null;
	}

	$file_content = $zip->getFromName($file_name);
	if ($file_content !== false)
	{
		$file_content_list = preg_split('/\n/', $file_content, -1, PREG_SPLIT_NO_EMPTY);
	}
	return $file_content_list !== false ? $file_content_list : null;
}

# phpcs:set VariableAnalysis.CodeAnalysis.VariableAnalysis validUnusedVariableNames
