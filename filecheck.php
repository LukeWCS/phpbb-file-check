<?php
/**
*
* phpBB File Check - Checks all relevant files of a phpBB installation for existence and integrity
*
* @copyright (c) 2023 LukeWCS <phpBB.de>
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
* PHP requirements: 7.1.0 - 8.4.x
*
*/

# phpcs:disable PSR1.Files.SideEffects
# phpcs:set VariableAnalysis.CodeAnalysis.VariableAnalysis validUnusedVariableNames messages

/*
	Initialization
*/
define('IS_BROWSER', ($_SERVER['HTTP_USER_AGENT'] ?? '') != '');

const EOL				= "\n";
const VALID_CHARS		= 'a-zA-Z0-9\/\-_.';
const ROOT_PATH			= __DIR__ . '/';
const EMPTY_FILE_HASH	= 'd41d8cd98f00b204e9800998ecf8427e';
const FC_ERROR			= 1;
const FC_WARNING		= 2;
const FC_NOTICE			= 3;
const MSG_PREFIXES		= [
	'FC_UNKNOWN: ',
	'FC_ERROR  : ',
	'FC_WARNING: ',
	'FC_NOTICE : ',
];
const VERSION_VARS		= [
	'{MAJOR}',
	'{MINOR}',
	'{PATCH}',
];

$ver					= '1.5.0';
$title					= "phpBB File Check v{$ver}";
$checksum_file_name		= 'filecheck';
$checksum_file_suffix	= '.md5';
$ignore_file			= 'filecheck_ignore.txt';
$exceptions_file		= 'filecheck_exceptions.txt';
$config_file			= 'filecheck_config.php';
$constants_file			= 'includes/constants.php';
$checksum_file			= $checksum_file_name . $checksum_file_suffix;
$checksum_diff_file		= $checksum_file_name . '_diff' . $checksum_file_suffix;
$checksum_version_mode	= 'Manually';
$checksum_file_flags	= [];
$config					= [];
$messages				= '';
$ignore_list			= [
	'^\.git',
	'\/\.git',
];
$exception_list			= [
	'docs/',
	'ext/phpbb/viglink/',
	'install/',
];
$ignore_unexpected_list	= [
	'^\.$',
	'^cache',
	'^ext',
	'^files',
	'^images',
	'^store',
];
$start_time				= microtime(true);
$output					= html_start();

/*
	Check services
*/
$service = [
	'ZipArchive'		=> extension_loaded('zip'),
	'cURL'				=> extension_loaded('curl'),
	'Sockets'			=> function_exists('fsockopen'),
	'allow_url_fopen'	=> (bool) ini_get('allow_url_fopen'),
];

/*
	Generate title
*/
$output .= $title . EOL;
$output .= str_repeat('=', strlen($title)) . EOL;

/*
	Include config
*/
if (file_exists(ROOT_PATH . $config_file))
{
	include ROOT_PATH . $config_file;
}
else
{
	message(FC_WARNING, "Config file [{$config_file}] not found.");
}
$config['debug_mode']		= $config['debug_mode']			?? 0;
$config['zip_url_pattern']	= $config['zip_url_pattern']	?? '';
$config['zip_name_pattern']	= $config['zip_name_pattern']	?? '';

$output .= ($config['debug_mode'] ? '(DEBUG MODE)' . EOL . EOL : EOL);

/*
	Get the phpBB version from constants.php
*/
if (file_exists(ROOT_PATH . $constants_file))
{
	$constants_content = @file_get_contents(ROOT_PATH . $constants_file);
	if ($constants_content !== false)
	{
		preg_match('/\'PHPBB_VERSION\'\s*,\s*\'([0-9]+\.[0-9]+\.[0-9]+)(.*?)\'/', $constants_content, $matches);
		$phpbb_version = $matches[1] ?? null;
		if ($phpbb_version !== null && !file_exists(ROOT_PATH . $checksum_file))
		{
			$checksum_file			= $checksum_file_name . '_' . $phpbb_version . $checksum_file_suffix;
			$checksum_diff_file		= $checksum_file_name . '_' . $phpbb_version . '_diff' . $checksum_file_suffix;
			$checksum_version_mode	= 'Auto';
		}
		if ($phpbb_version === null)
		{
			message(FC_WARNING, "phpBB version could not be determined from [{$constants_file}].");
		}
		else if (($matches[2] ?? '') != '')
		{
			terminate("Pre-releases of phpBB are not supported, {$matches[1]}{$matches[2]} found.");
		}
		else
		{
			$phpbb_version_segments = explode('.', $phpbb_version);
		}
	}
	else
	{
		message(FC_WARNING, "phpBB file [{$constants_file}] could not be read.");
	}
}
else
{
	message(FC_WARNING, "phpBB file [{$constants_file}] not found.");
}
$checksum_source = (isset($phpbb_version) && !file_exists(ROOT_PATH . $checksum_file)) ? 'ZIP' : 'Folder';
message(FC_NOTICE, "MD5 source: {$checksum_source}");

/*
	Automatic download and opening of the appropriate checksum package
*/
if ($checksum_source == 'ZIP')
{
	message(FC_WARNING, 'zip_url_pattern not set.',				$config['zip_url_pattern'] == '');
	message(FC_WARNING, 'zip_name_pattern not set.',			$config['zip_name_pattern'] == '');
	message(FC_WARNING, 'ZipArchive extension not available.',	!$service['ZipArchive']);
	message(FC_NOTICE, 'cURL extension not available.',			!$service['cURL']);
	message(FC_NOTICE, 'Sockets not available.',				!$service['Sockets']);
	message(FC_NOTICE, 'allow_url_fopen not enabled.',			!$service['allow_url_fopen']);

	$zip_url	= str_ireplace(VERSION_VARS, $phpbb_version_segments, $config['zip_url_pattern']);
	$zip_name	= str_ireplace(VERSION_VARS, $phpbb_version_segments, $config['zip_name_pattern']);

	if ($service['ZipArchive'] && $zip_name != '' && !file_exists(ROOT_PATH . $zip_name))
	{
		$save_test = file_put_contents(ROOT_PATH . $zip_name, 'save test') !== false;
		if ($save_test)
		{
			unlink(ROOT_PATH . $zip_name);
		}
		else
		{
			message(FC_WARNING, "No permission to create [{$zip_name}].");
		}
	}

	if (($save_test ?? false) && $zip_url != '')
	{
		message(FC_NOTICE, "Checksum package [{$zip_name}] not found.");

		if ($service['cURL'])
		{
			$curl_status = [];
			$zip_content = curl_get_contents($zip_url . $zip_name, $curl_status) ?? false;
			message(FC_NOTICE, 'cURL: HTTP code ' . ($curl_status['http_code'] ?? 0));
		}
		else if ($service['Sockets'])
		{
			preg_match('/(http[s]?):\/\/(.+?)(\/.+)\//', $zip_url, $matches);
			if (count($matches) == 4)
			{
				$socket_status = [];
				$socket_port = ($matches[1] == 'https') ? 443 : 80;
				$socket_host = $matches[2];
				$socket_dir = $matches[3];
				$zip_content = socket_get_contents($socket_host, $socket_dir, $zip_name, $socket_port, $socket_status) ?? false;
				message(FC_NOTICE, 'Sockets: ' . ($socket_status[0] ?? ''));
			}
			else
			{
				message(FC_WARNING, 'Sockets: The download URL could not be parsed.');
				$zip_content = false;
			}
		}
		else if ($service['allow_url_fopen'])
		{
			$zip_content = @file_get_contents($zip_url . $zip_name);
			message(FC_NOTICE, 'file_get_contents: ' . (($zip_content === false) ? 'false' : 'true'));
		}
		else
		{
			$zip_content = false;
		}

		if ($zip_content === false)
		{
			message(FC_WARNING, "Checksum package [{$zip_name}] could not be downloaded.");
		}
		else
		{
			if (substr($zip_content, 0, 4) === "PK\x03\x04")
			{
				if (file_put_contents(ROOT_PATH . $zip_name, $zip_content) === false)
				{
					message(FC_WARNING, "Checksum package [{$zip_name}] could not be saved.");
				}
			}
			else
			{
				message(FC_WARNING, 'The transferred data does not contain a valid ZIP archive.');
			}
		}
	}

	if ($service['ZipArchive'] && $zip_name != '' && file_exists(ROOT_PATH . $zip_name))
	{
		$zip = new ZipArchive;
		$zip_rc = $zip->open(ROOT_PATH . $zip_name);

		if ($zip_rc !== true)
		{
			unset($zip);
			$reflect_zip = new ReflectionClass('ZipArchive');
			$zip_er_constants = array_filter($reflect_zip->getConstants(), function ($key) {
				return substr($key, 0, 3) == 'ER_';
			}, ARRAY_FILTER_USE_KEY);
			unset($reflect_zip);
			$zip_er_constants = array_flip($zip_er_constants);
			message(FC_NOTICE, 'ZipArchive: ' . ($zip_er_constants[$zip_rc] ?? "RC: {$zip_rc}"));
			message(FC_WARNING, "Checksum package [{$zip_name}] could not be opened.");
		}
	}
}

/*
	Load the checksum files into the hash list
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
	load_checksum_file($checksum_file, $checksum_source, $checksum_name, $checksum_ver, $hash_list);

	if (isset($phpbb_version) && $checksum_ver != $phpbb_version)
	{
		terminate("Checksum file [{$checksum_file}] has the wrong version {$checksum_ver}.");
	}
	$checksum_file_flags[] = '1';
}
else
{
	terminate("Checksum file [{$checksum_file}] not found." . ($checksum_source == 'ZIP' && $zip_url != '' && $zip_name != ''
		? html_zip_instructions($zip_url . $zip_name, $service['ZipArchive'])
		: ''
	));
}

if ($checksum_source == 'Folder' && file_exists(ROOT_PATH . $checksum_diff_file)
	|| $checksum_source == 'ZIP' && zip_file_exists($checksum_diff_file)
)
{
	load_checksum_file($checksum_diff_file, $checksum_source, $checksum_diff_name, $checksum_diff_ver, $hash_list);

	if (isset($phpbb_version) && $checksum_diff_ver != $phpbb_version)
	{
		terminate("Checksum file [{$checksum_diff_file}] has the wrong version {$checksum_diff_ver}.");
	}
	$checksum_file_flags[] = '2';
}

$count_checksums		= count($hash_list);
$checksums_count_len	= strlen($count_checksums);

/*
	Load and check the external ignore list
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
$ignore_list_regex			= implode('|', $ignore_list);
$ignore_unexpected_regex	= implode('|', $ignore_unexpected_list);

/*
	Load and check the external exception list
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
	Display: versions and number of checksums
*/
$output .= sprintf('Version mode : %1$s', $checksum_version_mode) . EOL;
$output .= sprintf('MD5 source   : %1$s (%2$s)', $checksum_source, implode(', ', $checksum_file_flags)) . EOL;
$output .= sprintf('phpBB Version: %1$s', $phpbb_version ?? 'Unknown') . EOL;
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
	Core-Check
*/
$count_missing		= 0;
$count_different	= 0;
$count_error		= 0;
$count_warning		= 0;
$count_notice		= 0;
$count_ignored		= 0;
$count_exceptions	= 0;
$count_checked		= 0;
$result_core_list	= [];
$package_folders	= [];
foreach ($hash_list as $file => $hash_data)
{
	$dirname = dirname($file);
	if (!is_ignored($dirname, $ignore_unexpected_regex) && !array_key_exists($dirname, $package_folders))
	{
		$package_folders[$dirname] = '';
		while (strpos($dirname, '/') !== false)
		{
			$dirname = dirname($dirname);
			if (!array_key_exists($dirname, $package_folders))
			{
				$package_folders[$dirname] = '';
			}
		}
	}

	$is_ignored = is_ignored($file, $ignore_list_regex);
	if ($is_ignored || is_exception($file, $exception_list))
	{
		if ($config['debug_mode'])
		{
			if ($is_ignored)
			{
				$result_core_list[] = result_struct($file, $hash_data[0], '- IGNORED', '', $count_ignored);
			}
			else
			{
				$result_core_list[] = result_struct($file, $hash_data[0], '- EXCEPTION', '', $count_exceptions);
			}
		}
		continue;
	}

	$count_checked++;
	if (!file_exists(ROOT_PATH . $file))
	{
		$result_core_list[] = result_struct($file, $hash_data[0], '! MISSING', '', $count_missing);
		continue;
	}

	$calc_hash = @md5_file(ROOT_PATH . $file);
	if ($calc_hash !== false)
	{
		switch ($file)
		{
			case 'config.php':
				if ($calc_hash == EMPTY_FILE_HASH)
				{
					$result_core_list[] = result_struct($file, $hash_data[0], '! WARNING', 'has 0 bytes', $count_warning);
				}
				continue 2;
		}

		if ($hash_data[0]['hash'] != $calc_hash)
		{
			if (($hash_data[1]['hash'] ?? '') == $calc_hash)
			{
				$result_core_list[] = result_struct($file, $hash_data[1], '  NOTICE', 'has the ' . $checksum_diff_name . ' hash', $count_notice);
			}
			else if ($calc_hash == EMPTY_FILE_HASH)
			{
				$result_core_list[] = result_struct($file, $hash_data[0], '! WARNING', 'has 0 bytes', $count_warning);
			}
			else
			{
				$result_core_list[] = result_struct($file, $hash_data[0], '* DIFFERENT', '(hash: ' . $calc_hash . ')', $count_different);
			}
		}
	}
	else
	{
		$result_core_list[] = result_struct($file, $hash_data[0], '~ ERROR', 'MD5 hash could not be calculated', $count_error);
	}
}

/*
	Unexpected-Check
*/
$local_files			= [];
foreach ($package_folders as $folder => $dummy)
{
	$folder = ($folder === '.') ? '' : $folder . '/';
	$local_files = array_merge($local_files, array_filter(glob($folder . '{,.}*', GLOB_BRACE), 'is_file'));
}

$result_unexpected_list	= [];
foreach (array_diff($local_files, array_keys($hash_list)) as $key => $file)
{
	$hash_data = [
		'hash_file_id'	=> 0,
		'hash_line_num'	=> $key + 1,
	];
	$result_unexpected_list[] = result_struct($file, $hash_data, '! WARNING', 'is an unexpected file', $count_warning);
}

/*
	Format results and add to display buffer
*/
$output .= EOL . format_results($result_core_list, 'List of core files with anomalies');
$output .= EOL . format_results($result_unexpected_list, 'List of unexpected files');

/*
	Display: results and summary
*/
$summary = '';
if ($config['debug_mode'])
{
	$summary .=	sprintf('Ignored        : % ' . $checksums_count_len . 'u', $count_ignored) . EOL;
	$summary .=	sprintf('Exceptions     : % ' . $checksums_count_len . 'u', $count_exceptions) . EOL;
}
$summary .=		sprintf('Checked files  : % ' . $checksums_count_len . 'u', $count_checked) . EOL;
$summary .=		sprintf('Missing files  : % ' . $checksums_count_len . 'u', $count_missing) . EOL;
$summary .=		sprintf('Different files: % ' . $checksums_count_len . 'u', $count_different) . EOL;
if ($count_warning || $config['debug_mode'])
{
	$summary .=	sprintf('Warnings       : % ' . $checksums_count_len . 'u', $count_warning) . EOL;
}
if ($count_notice || $config['debug_mode'])
{
	$summary .=	sprintf('Notices        : % ' . $checksums_count_len . 'u', $count_notice) . EOL;
}
if ($count_error || $config['debug_mode'])
{
	$summary .=	sprintf('FC Errors      : % ' . $checksums_count_len . 'u', $count_error) . EOL;
}

$service_list = array_map(function (string $key, int $value) {
	return "$key:$value";
}, array_keys($service), array_values($service));

$exec_info =	sprintf('Run time          : %.3f seconds', microtime(true) - $start_time) . EOL;
$exec_info .=	sprintf('Max execution time: %u seconds', ini_get('max_execution_time')) . EOL;
$exec_info .=	sprintf('Memory peak usage : %s bytes', number_format(memory_get_peak_usage())) . EOL;
$exec_info .=	sprintf('Memory limit      : %s', ini_get('memory_limit')) . EOL;
$exec_info .=	sprintf('Services          : %s', implode(', ', $service_list)) . EOL;
$exec_info .=	sprintf('Timestamp         : %s', time()) . EOL;

$output .= EOL;
$output .= 'Finished!' . EOL;
$output .= EOL;
$output .= 'Report summary' . EOL;
$output .= str_repeat('-', column_max_len($summary)) . EOL;
$output .= $summary;
$output .= EOL;
$output .= 'Script/PHP information' . EOL;
$output .= str_repeat('-', column_max_len($exec_info)) . EOL;
$output .= $exec_info;
$output .= html_end();

flush_buffer();

/*
	Script end
*/

function result_struct(string &$file, array &$hash_data, string $msg_type, string $msg, int &$counter): array
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

function format_results(array $result_list, string $result_title = ''): string
{
	if (count($result_list) > 0)
	{
		$output = '';
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
				/* 5 */ (($row['msg'] != '') ? ' ' . $row['msg'] : '')
			) . EOL;
		}
	}
	else
	{
		$output = 'no issues found' . EOL ;
	}

	add_list_lines($output, strlen($result_title));

	return $result_title . (($result_title != '') ? EOL : '') . $output;
}

function is_ignored(string &$file, string &$ignore_list_regex): bool
{
	if ($ignore_list_regex === '' || preg_match('/' . $ignore_list_regex . '/', $file) !== 1)
	{
		return false;
	}

	return true;
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

function message(int $type, string $message, bool $condition = true): void
{
	if ($condition)
	{
		global $messages;

		$messages .= (MSG_PREFIXES[$type] ?? MSG_PREFIXES[FC_UNKNOWN]) . $message . EOL;
	}
}

function terminate(string $message): void
{
	global $output;
	global $messages;

	message(FC_ERROR, $message);
	$output .= $messages;
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
<div class="prevent-select">
	<hr>
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

function html_zip_instructions(string $zip_file_url, bool $zip_service): string
{
	if (IS_BROWSER)
	{
		$zip_file_url = "<a href=\"{$zip_file_url}\">{$zip_file_url}</a>";
	}

	$step_2 = ($zip_service
		? '2. Upload the archive as it is to the root directory of phpBB, i.e. where [config.php] is.'
		: '2. Unpack the archive and upload all unpacked files to the root directory of phpBB, i.e. where [config.php] is.'
	);

	$output = EOL . EOL . <<<"_HTML_"
Please follow these steps:
--------------------------
1. Download the following archive:
{$zip_file_url}
{$step_2}
3. Run phpBB File Check again.
_HTML_;

	return $output;
}

function load_checksum_file(string $checksum_file, string $checksum_source, string &$checksum_name, string &$checksum_ver, array &$hash_list): void
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

	preg_match('/^(.+?):([0-9]+\.[0-9]+\.[0-9]+)/', array_pop($checksums) ?? '', $matches);
	if (count($matches) == 3)
	{
		$checksum_name	= $matches[1];
		$checksum_ver	= $matches[2];
	}
	else
	{
		terminate("Checksum file [{$checksum_file}] does not have a valid version.");
	}

	static $hash_file_id	= 0;
	$line_num				= 0;
	$error_messages			= '';
	$hash_file_id++;
	foreach ($checksums as $row)
	{
		$line_num++;
		preg_match('/^([0-9a-f]{32}) \*([' . VALID_CHARS . ']+)$/', $row, $matches);
		if (count($matches) == 3)
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

function add_list_lines(string &$list, int $min_len = 0): void
{
	$column_len = column_max_len($list);
	$column_len = ($column_len < $min_len) ? $min_len : $column_len;
	$list_separator = str_repeat('-', $column_len);
	$list = $list_separator . EOL . $list . $list_separator . EOL ;
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

	return ($file_content_list !== false) ? $file_content_list : null;
}

function curl_get_contents(string $url, array &$status): ?string
{
	$curl_h = curl_init();
	curl_setopt_array($curl_h, [
		CURLOPT_URL             => $url,
		CURLOPT_RETURNTRANSFER  => 1,
		CURLOPT_CONNECTTIMEOUT  => 10,
		CURLOPT_HEADER          => 0,
		CURLOPT_SSL_VERIFYHOST  => 0,
		CURLOPT_SSL_VERIFYPEER  => 0,
	]);
	$content = curl_exec($curl_h);
	$status = curl_getinfo($curl_h) ?: [];
	curl_close($curl_h);

	return (($status['http_code'] ?? 0) == 200 && $content !== false) ? $content : null;
}

function socket_get_contents(string $host, string $directory, string $file, int $port, array &$status): ?string
{
	$socket_h = fsockopen((($port == 443) ? 'ssl://' : '') . $host, $port, $error_number, $error_string, 10);
	fwrite($socket_h, "GET {$directory}/{$file} HTTP/1.0\r\n");
	fwrite($socket_h, "HOST: {$host}\r\n");
	fwrite($socket_h, "Connection: close\r\n\r\n");

	$status = [];
	while (!feof($socket_h))
	{
		$line = trim(fgets($socket_h));
		if ($line == '')
		{
			break;
		}
		$status[] = $line;
	}
	$content = '';
	while (!feof($socket_h))
	{
		$content .= fread($socket_h, 4096);
	}
	fclose($socket_h);

	return (stripos($status[0] ?? '', '200 OK') !== false && $content != '') ? $content : null;
}

# phpcs:set VariableAnalysis.CodeAnalysis.VariableAnalysis validUnusedVariableNames
