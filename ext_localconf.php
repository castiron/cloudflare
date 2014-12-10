<?php
defined('TYPO3_MODE') or die();

$config = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]);
if (!is_array($config)) {
	$config = array();
}

// Register additional clear_cache method
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = 'EXT:' . $_EXTKEY . '/Classes/Hooks/TCEmain.php:Tx_Cloudflare_Hooks_TCEmain->clear_cacheCmd';

$versionParts = explode('.', TYPO3_version);
$version = intval((int) $versionParts[0] . str_pad((int) $versionParts[1], 3, '0', STR_PAD_LEFT) . str_pad((int) $versionParts[2], 3, '0', STR_PAD_LEFT));

$remoteIp = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REMOTE_ADDR');

// @see https://www.cloudflare.com/ips
$whiteListIPv4s = array(
	'199.27.128.0/21',
	'173.245.48.0/20',
	'103.21.244.0/22',
	'103.22.200.0/22',
	'103.31.4.0/22',
	'141.101.64.0/18',
	'108.162.192.0/18',
	'190.93.240.0/20',
	'188.114.96.0/20',
	'197.234.240.0/22',
	'198.41.128.0/17',
	'162.158.0.0/15',
	'104.16.0.0/12',
);
$whiteListIPv6s = array(
	'2400:cb00::/32',
	'2606:4700::/32',
	'2803:f800::/32',
	'2405:b500::/32',
	'2405:8100::/32',
);

$isProxied = FALSE;
if (isset($config['enableOriginatingIPs']) && $config['enableOriginatingIPs'] == 1) {
	if (\TYPO3\CMS\Core\Utility\GeneralUtility::validIPv6($remoteIp)) {
		$isProxied |= \TYPO3\CMS\Core\Utility\GeneralUtility::cmpIPv6($remoteIp, implode(',', $whiteListIPv6s));
	} else {
		$isProxied |= \TYPO3\CMS\Core\Utility\GeneralUtility::cmpIPv4($remoteIp, implode(',', $whiteListIPv4s));
	}
} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	// We take for granted that reverse-proxy is properly configured
	$isProxied = TRUE;
}

if ($isProxied) {
	// Flexible-SSL support
	if (isset($_SERVER['HTTP_CF_VISITOR'])) {
		$cloudflareVisitor = json_decode($_SERVER['HTTP_CF_VISITOR'], TRUE);
		if ($cloudflareVisitor['scheme'] === 'https') {
			$_SERVER['HTTPS'] = 'on';
			$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
			$_SERVER['SERVER_PORT'] = '443';
		}
	}

	// Cache SSL content
	if (isset($config['cacheSslContent']) && $config['cacheSslContent'] == 1) {
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nc_staticfilecache/class.tx_ncstaticfilecache.php']['createFile_initializeVariables'][] = 'EXT:' . $_EXTKEY . '/Classes/Hooks/tx_ncstaticfilecache.php:Tx_Cloudflare_Hooks_NcStaticfilecache->createFile_initializeVariables';
	}

	if (isset($config['enableOriginatingIPs']) && $config['enableOriginatingIPs'] == 1) {
		if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
	}
}

if (TYPO3_MODE === 'BE' && !empty($config['apiKey'])) {
	if ($config['showDevModeToggle'] === '1') {
		$cloudflareToolbarItemClassPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY, 'Classes/Hooks/TYPO3backend_Cloudflare.php');
		$GLOBALS['TYPO3_CONF_VARS']['typo3/backend.php']['additionalBackendItems'][] = $cloudflareToolbarItemClassPath;
	}


	if ($config['domains'] !== '' && $config['enableSeparateCloudFlareCacheClearing'] === '1') {
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearCloudflareCache'] = 'EXT:' . $_EXTKEY . '/Classes/Hooks/TYPO3backend.php:&Tx_Cloudflare_Hooks_TYPO3backend';
		$GLOBALS['TYPO3_CONF_VARS']['BE']['AJAX']['cloudflare::clearCache'] = 'EXT:' . $_EXTKEY . '/Classes/Hooks/TCEmain.php:Tx_Cloudflare_Hooks_TCEmain->clearCache';
	}
}
