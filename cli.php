#!/usr/bin/bash
<?php

define('BASE_DIR', '/opt/lampp/htdocs');
define('CONFIG_DIR', '/opt/lampp/apache2/generated_conf');
define('APACHE_CONFIG_FILE', '/opt/lampp/etc/httpd.conf');
define('PREFIX', 'test');

if (strtoupper(PHP_OS) != 'LINUX')
    die('this script only run with xampp linux!');

if (posix_getuid() !== '0')
    die('this script only run in linux!');

(array) $hosts = [];

function generateConfig()
{
    global $hosts;

    $dirs = scandir(BASE_DIR);
    $dirs = array_diff($dirs, array('.', '..'));

    if (!is_dir(CONFIG_DIR)) {
        mkdir(CONFIG_DIR, 0644, true);
    }

    foreach ($dirs as $dir) {
        $dirName = $dir;
        $dir = realpath(BASE_DIR . '/' . $dir);
        $templateFile = CONFIG_DIR . '/' . $dirName . '.vhost.conf';
        if (file_exists($templateFile)) {
            unlink($templateFile);
        }

        if (file_exists($dir) && is_dir($dir)) {
            echo $dir . PHP_EOL;
            $subdir = array_diff(scandir($dir), array('.', '..'));

            (bool) $publicFolder = in_array('public', $subdir);

            $serverName = $dirName . '.' . PREFIX;
            $documentRoot = $dir . ($publicFolder ? '/public' : '');

            $template = <<<EOD
    <VirtualHost *:80>
        ServerAdmin webmaster@localhost
        ServerName {$serverName}
        DocumentRoot {$documentRoot}
    
        <Directory {$documentRoot}>
            Options +FollowSymlinks
            AllowOverride All
            Require all granted
        </Directory>
    
        ErrorLog logs/error.log
        CustomLog logs/access.log combined
    </VirtualHost>
    EOD;


            $hosts[] = $serverName;
            file_put_contents($templateFile, $template);
        }
    }

}

function isComment($str)
{
    $str = trim($str);
    $first_two_chars = substr($str, 0, 2);
    $last_two_chars = substr($str, -2);
    return $first_two_chars == '//' || substr($str, 0, 1) == '#' || ($first_two_chars == '/*' && $last_two_chars == '*/');
}


function generateHost()
{
    global $hosts;

    $existingHosts = [];
    $fh = @fopen('/etc/hosts', 'r');

    if (!$fh) {
        throw new \Exception('unable to read hosts!');
    }

    while (($line = fgets($fh)) !== false) {
        $line = str_replace(array("\r", "\n"), '', $line);

        if (empty($line) || isComment($line)) {
            continue;
        }

        $parts = preg_split('/[\t\s+]/', $line);
        $parts = array_filter($parts, fn($v) => trim($v) !== '');
        $parts = array_values($parts);
        // var_dump($parts);
        if (in_array($parts[1], $hosts) || count($parts) == 1) {
            continue;
        }
        $existingHosts[] = $parts;
    }
    fclose($fh);

    return $existingHosts;
}

generateConfig();
$gh = array_map(fn($v) => implode(' ', $v), generateHost());

$_temp = '';
foreach ($hosts as $host) {
    $_temp .= '127.0.0.1 ' . $host . PHP_EOL;
}
$_temp .= implode(PHP_EOL, $gh);

file_put_contents('/etc/hosts', $_temp);
exec('/bin/systemctl restart systemd-hostnamed');

exec('/opt/lampp/xampp startapache', $output);
if ($output[0] == 'XAMPP: Starting Apache...already running.')
    exec('/opt/lampp/xampp reloadapache');