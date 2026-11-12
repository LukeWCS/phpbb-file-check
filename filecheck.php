<?php
/**
*
* phpBB File Check - Checks all relevant files of a phpBB installation for existence and integrity
*
* @copyright (c) 2023 LukeWCS <phpBB.de>
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
* PHP requirements: 7.2.0 - 8.6.x
*
*/

# phpcs:disable PSR1.Files.SideEffects
# phpcs:set VariableAnalysis.CodeAnalysis.VariableAnalysis validUnusedVariableNames messages output

/*
	Initialization
*/

mb_internal_encoding('UTF-8');

define('IS_BROWSER'		, ($_SERVER['HTTP_USER_AGENT'] ?? '') != '');
define('SERVER_SOFTWARE', trim(preg_replace('/.*?([a-zA-Z][a-zA-Z_\-]+).*/', '$1', $_SERVER['SERVER_SOFTWARE'] ?? ''), " \n\r\t\v\x00") ?: null);

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

$ver					= '1.6.1';
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
$output					= '';
$detail_sep				= ' | ';
$core_ignore_list		= [
	'^\.git',
	'\/\.git',
];
$core_exception_list	= [
	'docs/',
	'ext/phpbb/viglink/',
	'install/',
];
$unexpected_ignore_list	= [
	'^\.$',
	'^cache',
	'^ext',
	'^files',
	'^images',
	'^store',
];
$unexpected_temp_list	= [
	'store/install_config.php',
	'store/io_lock.lock',
];
$start_time				= microtime(true);

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
$output .= str_repeat('=', mb_strlen($title)) . EOL;

/*
	Include config
*/

if (file_exists(ROOT_PATH . $config_file))
{
	include ROOT_PATH . $config_file;
}
else
{
	message(FC_WARNING, "Config: file [{$config_file}] not found.");
}
$config['debug_mode']		= $config['debug_mode']			?? 0;
$config['show_extensions']	= $config['show_extensions']	?? 0;
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
	message(FC_WARNING	, 'Config: zip_url_pattern not set.',				$config['zip_url_pattern'] == '');
	message(FC_WARNING	, 'Config: zip_name_pattern not set.',				$config['zip_name_pattern'] == '');
	message(FC_WARNING	, 'Service: ZipArchive extension not available.',	!$service['ZipArchive']);
	message(FC_NOTICE	, 'Service: cURL extension not available.',			!$service['cURL']);
	message(FC_NOTICE	, 'Service: Sockets not available.',				!$service['Sockets']);
	message(FC_NOTICE	, 'Service: allow_url_fopen not enabled.',			!$service['allow_url_fopen']);

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
			message(FC_WARNING, "Download: No permission to create [{$zip_name}].");
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
				$socket_status	= [];
				$socket_port	= ($matches[1] == 'https') ? 443 : 80;
				$socket_host	= $matches[2];
				$socket_dir		= $matches[3];
				$zip_content	= socket_get_contents($socket_host, $socket_dir, $zip_name, $socket_port, $socket_status) ?? false;
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
			message(FC_WARNING, "Download: Checksum package [{$zip_name}] could not be downloaded.");
		}
		else
		{
			if (substr($zip_content, 0, 4) === "PK\x03\x04")
			{
				if (file_put_contents(ROOT_PATH . $zip_name, $zip_content) === false)
				{
					message(FC_WARNING, "Download: Checksum package [{$zip_name}] could not be saved.");
				}
			}
			else
			{
				message(FC_WARNING, 'Download: The transferred data does not contain a valid ZIP archive.');
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
			$zip_er_constants = array_filter($reflect_zip->getConstants(), function (string $key): bool {
				return substr($key, 0, 3) == 'ER_';
			}, ARRAY_FILTER_USE_KEY);
			unset($reflect_zip);
			$zip_er_constants = array_flip($zip_er_constants);
			message(FC_NOTICE, 'ZipArchive: ' . ($zip_er_constants[$zip_rc] ?? "RC: {$zip_rc}"));
			message(FC_WARNING, "Download: Checksum package [{$zip_name}] could not be opened.");
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
	|| $checksum_source == 'ZIP' && zip_file_exists($zip, $checksum_file)
)
{
	load_checksum_file($checksum_file, $checksum_name, $checksum_ver, $hash_list, $zip ?? null);

	if (isset($phpbb_version) && $checksum_ver != $phpbb_version)
	{
		terminate("Checksum file [{$checksum_file}] has the wrong version {$checksum_ver}.");
	}
	$checksum_file_flags[] = '1';
}
else
{
	if ($checksum_source == 'ZIP' && $zip_url != '' && $zip_name != '')
	{
		$zip_instructions = "\n\n" .
			"Please follow these steps:\n" . add_list_lines(
			"1. Download the following archive:\n" .
			"   {$zip_url}{$zip_name}\n" .
			"2. " . ($service['ZipArchive']
				? "Upload the archive as it is to the root directory of phpBB, i.e. where [config.php] is.\n"
				: "Unpack the archive and upload all unpacked files to the root directory of phpBB, i.e. where [config.php] is.\n") .
			"3. Run phpBB File Check again.\n");
	}
	terminate("Checksum file [{$checksum_file}] not found." . $zip_instructions ?? '');
}

if ($checksum_source == 'Folder' && file_exists(ROOT_PATH . $checksum_diff_file)
	|| $checksum_source == 'ZIP' && zip_file_exists($zip, $checksum_diff_file)
)
{
	load_checksum_file($checksum_diff_file, $checksum_diff_name, $checksum_diff_ver, $hash_list, $zip ?? null);

	if (isset($phpbb_version) && $checksum_diff_ver != $phpbb_version)
	{
		terminate("Checksum file [{$checksum_diff_file}] has the wrong version {$checksum_diff_ver}.");
	}
	$checksum_file_flags[] = '2';
}

$count_checksums		= count($hash_list);
$checksums_count_len	= strlen((string) $count_checksums);

/*
	Load and check the external ignore list
*/
if ($checksum_source == 'Folder' && file_exists(ROOT_PATH . $ignore_file)
	|| $checksum_source == 'ZIP' && zip_file_exists($zip, $ignore_file)
)
{
	if ($checksum_source == 'Folder')
	{
		$import_list = @file(ROOT_PATH . $ignore_file, FILE_IGNORE_NEW_LINES);
	}
	else
	{
		$import_list = zip_extract_to_array($zip, $ignore_file) ?? false;
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
			terminate("Ignore list [{$ignore_file}] has the following issues:" . EOL . add_list_lines($error_messages));
		}
		$core_ignore_list = array_merge($core_ignore_list, $import_list);
	}
	else
	{
		terminate("Ignore list [{$ignore_file}] could not be read.");
	}
	$checksum_file_flags[] = 'I';
}

$core_ignore_regex			= implode('|', $core_ignore_list);
$unexpected_ignore_regex	= implode('|', $unexpected_ignore_list);

/*
	Load and check the external exception list
*/

if ($checksum_source == 'Folder' && file_exists(ROOT_PATH . $exceptions_file)
	|| $checksum_source == 'ZIP' && zip_file_exists($zip, $exceptions_file)
)
{
	if ($checksum_source == 'Folder')
	{
		$import_list = @file(ROOT_PATH . $exceptions_file, FILE_IGNORE_NEW_LINES);
	}
	else
	{
		$import_list = zip_extract_to_array($zip, $exceptions_file) ?? false;
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
			terminate("Exception list [{$exceptions_file}] has the following issues:" . EOL . add_list_lines($error_messages));
		}
		$core_exception_list = array_merge($core_exception_list, $import_list);
		sort($core_exception_list);
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
$output .= sprintf('PHP Version  : %1$u.%2$u (%3$s)', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_OS) . EOL;
$output .= sprintf('Server       : %1$s', SERVER_SOFTWARE ?? (IS_BROWSER ? 'Unknown' : 'cli')) . EOL;
$output .= EOL;
$output .= "Please wait, {$count_checksums} checksums are being processed..." . EOL;

if (IS_BROWSER)
{
	if (session_start() && ($_SESSION['exec_check'] ?? 0) !== 1)
	{
		$_SESSION['exec_check'] = 1;
		header('refresh: 0; url=filecheck.php');
		send_output($output, 'wait');
		exit;
	}
	$_SESSION = [];
}
else
{
	send_output($output);
}

/*
	Core-check
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
$start_time_core	= microtime(true);

foreach ($hash_list as $file => $hash_data)
{
	$is_ignored = is_ignored($file, $core_ignore_regex);
	if ($is_ignored || is_exception($file, $core_exception_list))
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
				$result_core_list[] = result_struct($file, $hash_data[1], '  NOTICE', "has the {$checksum_diff_name} hash", $count_notice);
			}
			else if ($calc_hash == EMPTY_FILE_HASH)
			{
				$result_core_list[] = result_struct($file, $hash_data[0], '! WARNING', 'has 0 bytes', $count_warning);
			}
			else
			{
				$file_size = (($tmp_size = @filesize(ROOT_PATH . $file)) !== false) ? number_format($tmp_size) . ' bytes' : 'ERROR';
				$result_core_list[] = result_struct($file, $hash_data[0], '* DIFFERENT', "(Hash: {$calc_hash}{$detail_sep}Size: {$file_size})", $count_different);
			}
		}
	}
	else
	{
		$result_core_list[] = result_struct($file, $hash_data[0], '~ ERROR', 'MD5 hash could not be calculated', $count_error);
	}
}

$runtime_core = microtime(true) - $start_time_core;

/*
	Unexpected-check
*/

$count_unexpected		= 0;
$package_folders		= [];
$local_files			= [];
$local_files_diff		= [];
$result_unexpected_list	= [];
$hash_data_zero			= ['hash_file_id' => 0, 'hash_line_num' => 0];
$start_time_unexpected	= microtime(true);

foreach ($hash_list as $file => $_)
{
	$dirname = dirname($file);
	if (!array_key_exists($dirname, $package_folders) && !is_ignored($dirname, $unexpected_ignore_regex))
	{
		$package_folders[$dirname] = '';
		while (strpos($dirname, '/') !== false)
		{
			$dirname = dirname($dirname);
			if (array_key_exists($dirname, $package_folders))
			{
				break;
			}
			$package_folders[$dirname] = '';
		}
	}
}

foreach ($package_folders as $folder => $_)
{
	$folder = ($folder === '.') ? '' : $folder . '/';
	$local_files = array_merge($local_files, glob(ROOT_PATH . $folder . '{.[!.],}*', GLOB_BRACE));
}
$local_files = str_replace(ROOT_PATH, '', $local_files);

$local_files_diff = array_diff($local_files, array_keys($hash_list));
$local_files_diff = array_filter($local_files_diff, function (string $file): bool {
	return is_file(ROOT_PATH . $file);
});

$get_file_info = function (string $file, ?string &$calc_hash, ?string &$file_size): void
{
	$calc_hash = (($tmp_hash = @md5_file(ROOT_PATH . $file)) !== false) ? $tmp_hash : 'ERROR';
	$file_size = (($tmp_size = @filesize(ROOT_PATH . $file)) !== false) ? number_format($tmp_size) . ' bytes' : 'ERROR';
};

foreach ($local_files_diff as $file)
{
	$get_file_info($file, $calc_hash, $file_size);
	$result_unexpected_list[] = result_struct($file, $hash_data_zero, '! UNEXPECTED', "(Hash: {$calc_hash}{$detail_sep}Size: {$file_size})", $count_unexpected);
}

foreach ($unexpected_temp_list as $file)
{
	if (file_exists(ROOT_PATH . $file))
	{
		$get_file_info($file, $calc_hash, $file_size);
		$result_unexpected_list[] = result_struct($file, $hash_data_zero, '! TEMPORARY', "(Hash: {$calc_hash}{$detail_sep}Size: {$file_size})", $count_unexpected);
	}
}

$runtime_unexpected = microtime(true) - $start_time_unexpected;

/*
	Identify available extensions
*/

$result_extensions_list = [];
$status_extensions		= [];
$count_extensions		= [
	'all'			=> 0,
	'enabled'		=> 0,
	'disabled'		=> 0,
	'not_installed'	=> 0,
];
$ext_folder				= str_replace('\\', '/', ROOT_PATH . 'ext/');
$start_time_extensions	= microtime(true);

if ($config['show_extensions'] && file_exists(ROOT_PATH . 'config.php') && filesize(ROOT_PATH . 'config.php'))
{
	$config_php_content	= file_get_contents(ROOT_PATH . 'config.php');
	$get_phpbb_var		= function (string $varname) use ($config_php_content): string
	{
		preg_match('/\$' . $varname . '\s*=\s*\'(.*?)\'/', $config_php_content, $matches);
		return $matches[1] ?? '';
	};

	$dbms			= $get_phpbb_var('dbms');
	$dbhost			= $get_phpbb_var('dbhost');
	$dbport			= $get_phpbb_var('dbport');
	$dbname			= $get_phpbb_var('dbname');
	$dbuser			= $get_phpbb_var('dbuser');
	$dbpasswd		= $get_phpbb_var('dbpasswd');
	$table_prefix	= $get_phpbb_var('table_prefix');

	if (!empty($dbms))
	{
		/* phpbb/install/helper/database.php: $supported_dbms */
		$pdo_dbms = [
			'phpbb\db\driver\mysqli'		=> 'mysql',
			'phpbb\db\driver\mssqlnative'	=> 'sqlsrv',
			'phpbb\db\driver\postgres'		=> 'pgsql',
			'phpbb\db\driver\sqlite3'		=> 'sqlite',
			'phpbb\db\driver\mssql_odbc'	=> 'not-supported', /* odbc */
			'phpbb\db\driver\oracle'		=> 'not-supported', /* oci */
		][str_replace('\\\\', '\\', $dbms)]	?? 'unknown';

		message(FC_WARNING, "PDO: Not supported DBMS: {$dbms}"	, $pdo_dbms == 'not-supported');
		message(FC_WARNING, "PDO: Unknown DBMS: {$dbms}"		, $pdo_dbms == 'unknown');

		$service['PDO'] = $pdo_dbms;

		if (!in_array($pdo_dbms, ['unknown', 'not-supported']))
		{
			$pdo_dsn = [
				'mysql'		=> "{$pdo_dbms}:" .
					($dbhost ? "host={$dbhost};" : '') .
					($dbport ? "port={$dbport};" : '') .
					($dbname ? "dbname={$dbname};" : '') .
					'charset=utf8mb4;',
				'sqlsrv'	=> "{$pdo_dbms}:" .
					($dbhost ? "Server={$dbhost}" : '') . ($dbhost && !$dbport ? ';' : '') . ($dbhost && $dbport ? ",{$dbport};" : '') .
					($dbname ? "Database={$dbname};" : ''),
				'pgsql'		=> "{$pdo_dbms}:" .
					($dbhost ? "host={$dbhost};" : '') .
					($dbport ? "port={$dbport};" : '') .
					($dbname ? "dbname={$dbname};" : ''),
				'sqlite'	=> "{$pdo_dbms}:" .
					($dbname ? "{$dbname}" : ''),
			][$pdo_dbms];
			$pdo_options = [
				PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC,
			];

			try
			{
				$pdo = new PDO($pdo_dsn, $dbuser, $dbpasswd, $pdo_options);
				$sql = 'SELECT ext_name, ext_active
						FROM ' . $table_prefix . 'ext';
				$result = $pdo->query($sql);
				$exts_db = $result->fetchAll();
				$pdo = null;

				$status_extensions = array_combine(array_column($exts_db, 'ext_name'), array_column($exts_db, 'ext_active'));
			}
			catch (\PDOException $e)
			{
				if ($config['debug_mode'])
				{
					message(FC_WARNING, 'PDO: ' . $e->getMessage());
				}
			}
		}
	}
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ext_folder, RecursiveDirectoryIterator::SKIP_DOTS));
$iterator->setMaxDepth(2);
foreach ($iterator as $file)
{
	if ($iterator->getDepth() == 2 && $file->getFilename() == 'composer.json')
	{
		$composer_pathname	= str_replace('\\', '/', $file->getPathname());
		$composer_content	= @file_get_contents($composer_pathname);
		if ($composer_content !== false)
		{
			$composer_ary 	= json_decode($composer_content, true);
			$ext_info		= [
				'name'			=> $composer_ary['name']									?? false,
				'homepage'		=> $composer_ary['homepage']								?? false,
				'version'		=> $composer_ary['version']									?? false,
				'php'			=> $composer_ary['require']['php']							?? false,
				'display_name'	=> $composer_ary['extra']['display-name']					?? false,
				'phpbb'			=> $composer_ary['extra']['soft-require']['phpbb/phpbb']	?? false,
			];
			$msg_type		= [
				1	=> '2 EXTENSION',
				0	=> '1 EXTENSION',
				-1	=> '0 EXTENSION',
				-2	=> '  EXTENSION',
			][$status_extensions[$ext_info['name']] ?? ($status_extensions ? -1 : -2)];

			if ($ext_info['name'] !== false && $ext_info['display_name'] !== false && $ext_info['version'] !== false
				&& dirname($composer_pathname, 1) == $ext_folder . $ext_info['name'])
			{
				$ext_details = implode($detail_sep, preg_grep('/.+/', [
					($detail = $ext_info['phpbb'])		? 'phpBB: '		. $detail : '',
					($detail = $ext_info['php'])		? 'PHP: '		. $detail : '',
					($detail = $ext_info['homepage'])	? 'Homepage: '	. $detail : '',
				]));

				$hash_data = ['file' => $ext_info['display_name'], 'hash_file_id' => 0, 'hash_line_num' => 0];

				$result_extensions_list[] = result_struct("{$ext_info['display_name']} v{$ext_info['version']}", $hash_data, $msg_type, "({$ext_details})", $count_extensions['all']);

				if ($status_extensions)
				{
					$count_extensions[[
						'2 EXTENSION'	=> 'enabled',
						'1 EXTENSION'	=> 'disabled',
						'0 EXTENSION'	=> 'not_installed',
					][$msg_type]]++;
				}
			}
		}
	}
}

$runtime_extensions = microtime(true) - $start_time_extensions;

/*
	Format results and add to display buffer
*/

$output .= EOL . format_results($result_core_list, 'List of core files with anomalies', 'files');
$output .= EOL . format_results($result_unexpected_list, 'List of unexpected files', 'files');
if ($config['show_extensions'])
{
	$output .= EOL . format_results($result_extensions_list, 'List of available Extensions', 'extensions');
}

/*
	Display: results and summary
*/

$summary = '';
if ($config['debug_mode'])
{
	$summary .=	sprintf('Ignored         : % ' . $checksums_count_len . 'u', $count_ignored) . EOL;
	$summary .=	sprintf('Exceptions      : % ' . $checksums_count_len . 'u', $count_exceptions) . EOL;
}
$summary .=		sprintf('Checked files   : % ' . $checksums_count_len . 'u', $count_checked) . EOL;
$summary .=		sprintf('Missing files   : % ' . $checksums_count_len . 'u', $count_missing) . EOL;
$summary .=		sprintf('Different files : % ' . $checksums_count_len . 'u', $count_different) . EOL;
$summary .=		sprintf('Unexpected files: % ' . $checksums_count_len . 'u', $count_unexpected) . EOL;
$summary .=		sprintf('Extensions      : %1$ ' . $checksums_count_len . 'u' . ($status_extensions ? ' (Enabled:%2$u, Disabled:%3$u, Not-Installed:%4$u)' : ''),
	$count_extensions['all'], $count_extensions['enabled'], $count_extensions['disabled'], $count_extensions['not_installed']) . EOL;
if ($count_warning || $config['debug_mode'])
{
	$summary .=	sprintf('Warnings        : % ' . $checksums_count_len . 'u', $count_warning) . EOL;
}
if ($count_notice || $config['debug_mode'])
{
	$summary .=	sprintf('Notices         : % ' . $checksums_count_len . 'u', $count_notice) . EOL;
}
if ($count_error || $config['debug_mode'])
{
	$summary .=	sprintf('FC Errors       : % ' . $checksums_count_len . 'u', $count_error) . EOL;
}

$service_list = array_map(function (string $key, $value): string {
	return "$key:$value";
}, array_keys($service), array_values($service));

$exec_info =	sprintf('Run time          : %.3f seconds (Core-check: %.3f, Unexpected-check: %.3f, Extensions: %.3f)', microtime(true) - $start_time, $runtime_core, $runtime_unexpected, $runtime_extensions) . EOL;
$exec_info .=	sprintf('Max execution time: %u seconds', ini_get('max_execution_time')) . EOL;
$exec_info .=	sprintf('Memory peak usage : %s bytes', number_format(memory_get_peak_usage())) . EOL;
$exec_info .=	sprintf('Memory limit      : %s', ini_get('memory_limit')) . EOL;
$exec_info .=	sprintf('Services          : %s', implode(', ', $service_list)) . EOL;
$exec_info .=	sprintf('Timestamp         : %s', time()) . EOL;

$output .= EOL;
$output .= 'Finished!' . EOL;
$output .= EOL;
$output .= 'Report summary' . EOL;
$output .= add_list_lines($summary, 0, 'upper');
$output .= EOL;
$output .= 'Script/PHP information' . EOL;
$output .= add_list_lines($exec_info, 0, 'upper');

if ($config['debug_mode'])
{
	$output .= EOL;
	$output .= 'Debug messages' . EOL;
	$output .= '--------------' . EOL;

	$output .= $messages;
}

send_output($output, 'button');

/*
	Script end
*/

function result_struct(string $file, array $hash_data, string $msg_type, string $msg, int &$counter): array
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

function format_results(array $result_list, string $result_title, string $list_type): string
{
	if (count($result_list) > 0)
	{
		$output = '';
		if ($list_type == 'extensions')
		{
			array_multisort(
				array_column($result_list, 'msg_type'), SORT_DESC,
				array_column($result_list, 'file'), SORT_NATURAL + SORT_FLAG_CASE,
				$result_list
			);
		}
		else if ($list_type == 'files')
		{
			uasort($result_list, function($a, $b) {
				return [$a['path'], $a['file']] <=> [$b['path'], $b['file']];
			});
		}

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
		$output = ($list_type == 'extensions' ? 'no extensions found' : 'no issues found') . EOL;
	}

	return $result_title . ($result_title ? EOL : '') . add_list_lines($output, mb_strlen($result_title));
}

function is_ignored(string $file, string $ignore_regex): bool
{
	if ($ignore_regex === '' || preg_match('/' . $ignore_regex . '/', $file) !== 1)
	{
		return false;
	}

	return true;
}

function is_exception(string $file, array $exception_list): bool
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
	send_output($output);
	exit;
}

function load_checksum_file(string $checksum_file, string &$checksum_name, string &$checksum_ver, array &$hash_list, ?object $zip): void
{
	if ($zip === null)
	{
		$checksums = @file(ROOT_PATH . $checksum_file, FILE_IGNORE_NEW_LINES);
	}
	else
	{
		$checksums = zip_extract_to_array($zip, $checksum_file) ?? false;
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
		terminate("Checksum file [{$checksum_file}] has the following issues:" . EOL . add_list_lines($error_messages));
	}
}

function add_list_lines(string $list, int $min_len = 0, string $lines_pos = 'both'): string
{
	$column_len = column_max_len($list);
	$column_len = ($column_len < $min_len) ? $min_len : $column_len;
	$list_separator = str_repeat('-', $column_len);
	return	(in_array($lines_pos, ['upper', 'both']) ? $list_separator . EOL : '') .
			$list .
			(in_array($lines_pos, ['lower', 'both']) ? $list_separator . EOL : '');
}

function column_max_len($list, string $column_name = ''): int
{
	$column = is_array($list) ? array_column($list, $column_name) : explode(EOL, $list);

	return max(array_map('mb_strlen', $column));
}

function zip_file_exists(?object $zip, string $file_name): bool
{
	return $zip !== null && $zip->locateName($file_name) !== false;
}

function zip_extract_to_array(?object $zip, string $file_name): ?array
{
	if ($zip === null)
	{
		return null;
	}

	$file_content		= $zip->getFromName($file_name);
	$file_content_list	= false;
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
		$line = trim(fgets($socket_h), " \n\r\t\v\x00");
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

function send_output(string &$text, string $mode = ''): void
{
	global $title;

	if (!IS_BROWSER)
	{
		echo $text;
		$text = '';
		return;
	}

# phpcs:disable phpbb.ControlStructures.OpeningBraceBsdAllman.BraceOnSameLine
# phpcs:disable Squiz.ControlStructures.ElseIfDeclaration.NotAllowed

?>
<!DOCTYPE HTML>
<html>
	<head>
		<title>phpBB File Check</title>
		<meta name="robots" content="noindex">
		<style>
			body {
				font-family: "Trebuchet MS", sans-serif;
				margin: 1em;
				background-color: #f5f5f5;
			}
			h1 {
				font-size: 1.1em;
				font-weight: normal;
			}
			textarea {
				padding: 0.25em 0.5em;
				font-size: 1.1em;
				width: 100%;
				height: calc(100vh - 10em);
				box-sizing: border-box;
				resize: none;
			}
			#message {
				font-family: verdana;
				font-size: 0.8em;
			}
			.msg-wait:before {
				content: "\23F3\0020";
			}
			.msg-success:before {
				color: limegreen;
				content: "\2714\0020";
			}
			.msg-error:before {
				color: red;
				content: "\2716\0020";
			}
		</style>
	</head>
	<body>
		<h1><?php echo $title; ?></h1>

		<textarea name="report" rows="30" cols="135" wrap="off" readonly><?php echo "[code]\n" . $text . "[/code]"; ?></textarea>

<?php if ($mode == 'wait'): ?>
		<p>
			<span id="message" class="msg-wait">please wait...</span>
		</p>
<?php elseif ($mode == 'button'): ?>
		<p>
			<button name="copy">Copy report to clipboard</button> <span id="message" hidden>message</span>
		</p>

		<script>
			function copyReport() {
				const button		= this;
				const message		= document.querySelector('#message');
				const textContainer	= document.querySelector('textarea')

				button.disabled = true;
				textContainer.select();
				try {
					if (typeof navigator !== 'undefined' && navigator.clipboard) {
						navigator.clipboard.writeText(textContainer.value);
					} else {
						document.execCommand('copy');
					}
					message.className = 'msg-success';
					message.innerHTML = 'copy successful';
				} catch (err) {
					console.error('clipboard copy error: ' + err);
					message.className = 'msg-error';
					message.innerHTML = 'copy failed!';
				}

				message.hidden = false;
				setTimeout(function() {
					message.hidden = (message.className == 'msg-success');
					button.disabled = false;
				}, 3000);
			}

			document.addEventListener('DOMContentLoaded', function() {
				document.querySelector('button[name="copy"]').addEventListener('click', copyReport);
			});
		</script>
<?php endif; ?>
	</body>
</html>
<?php
}
