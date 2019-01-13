<?php
//  Copyright (C) 2010 CVT FIT Brno University of Technology
//  All Rights Reserved. See LICENSE.
//  Petr Lampa <lampa@fit.vutbr.cz>
//  $Id: cosign.php,v 1.3 2010/04/02 09:38:00 lampa Exp lampa $
//  vi:set ts=8 sts=4 sw=4:


define("LOCAL_CONFIG", ".cosign.php");
define("IDLE_TIME", "60");
define("SOCKET_TIMEOUT", "10");

function cosign_debug($str)
{
    global $cosign_cfg;
    global $cosign_log;

    if (empty($cosign_cfg['CosignFilterDebug'])) return;
    if (!is_resource($cosign_log)) $cosign_log = @fopen($cosign_cfg['CosignFilterLog'], "a");
    @fwrite($cosign_log, date("Y-m-d H:i:s ").$str."\n");
}

// configuration is merged from global cosign_config.php, .cosign.php
function cosign_auth($cfg = array(), $obstart = true)
{
    global $cosign_cfg;
    global $cosign_log;

    $level = error_reporting(E_ALL);
    if ($obstart) ob_start();
    $cosign_cfg = array();
    require_once("cosign_config.php");
    if (file_exists(LOCAL_CONFIG)) {
	require_once(LOCAL_CONFIG);
    }
    $cosign_cfg = array_merge($cosign_cfg, $cfg);
    if (empty($cosign_cfg['CosignProtected'])) {
	error_reporting($level);
	return false;
    }
    $service = "CosignFilter";

    $service_cookie = "cosign-".$cosign_cfg["CosignService"];
    $rekey_service = false;
    $dest = '';
    // Cosign v3 validation service
    if (isset($cosign_cfg['CosignValidLocation']) &&
        $_SERVER['SCRIPT_NAME'] == $cosign_cfg['CosignValidLocation']) {
	$rekey_service = true;
	$service = "CosignValid";
	if ($_SERVER['REQUEST_METHOD'] != 'GET' ||
	    !isset($cosign_cfg['CosignValidationErrorRedirect']) ||
	    !isset($cosign_cfg['CosignValidReference']) ||
	    $_SERVER['QUERY_STRING'] == '' ||
	    ($p = strpos($_SERVER['QUERY_STRING'], '&')) === false) {
	    cosign_debug("CosignValid: Invalid validation request");
	    ob_end_flush();
	    header("503 Service Temporarily Unavailable");
	    echo "Invalid validation request";
	    exit();
	}
	$service_cookie_val = substr($_SERVER['QUERY_STRING'], 0, $p);
	if (strncmp($service_cookie, $service_cookie_val, strlen($service_cookie)) != 0) {
	    cosign_debug("CosignValid: Invalid service $service_cookie_val");
	    ob_end_flush();
	    header("Location: {$cosign_cfg['CosignValidationErrorRedirect']}");
	    exit();
	}
	$service_cookie_val = substr($service_cookie_val, strlen($service_cookie)+1);
	$dest = substr($_SERVER['QUERY_STRING'], $p+1);
	if (!preg_match($cosign_cfg['CosignValidReference'], $dest)) {
	    cosign_debug("CosignValid: Invalid validation destination $dest");
	    ob_end_flush();
	    header("Location: {$cosign_cfg['CosignValidationErrorRedirect']}");
	    exit();
	}
	cosign_debug("CosignValid: Service cookie $service_cookie_val dest $dest"); 
    } else
    // check if cookie is present in the request
    if (!isset($_COOKIE[$service_cookie])) {
	cosign_debug(print_r($_COOKIE, true));
	cosign_debug("$service: Service cookie not present, redirecting to login");
	cosign_set_cookie_and_redirect();
    } else {
	// PHP always URL decodes cookie values, + is changed to space
	$service_cookie_val = str_replace(' ', '+', $_COOKIE[$service_cookie]);
	cosign_debug("$service: Service cookie $service_cookie present"); 
    }

    // check cookie expiration
    if (($p = strpos($service_cookie_val, '/')) !== false) {
	$ts = intval(substr($service_cookie_val, $p+1));
	// if post, don't redirect, wait for next request
	if (strcasecmp($_SERVER['REQUEST_METHOD'], "post") != 0 &&
	    $ts + $cosign_cfg['CosignCookieExpireTime'] < time()) {
	    cosign_debug("$service: Service cookie expired, redirecting to login");
	    cosign_set_cookie_and_redirect();
	}
    } else $p = strlen($service_cookie_val);
    if ($p < 100 || $p > 128) {
	cosign_debug("$service: Invalid service cookie length $p");
	if ($rekey_service) return false;
	cosign_set_cookie_and_redirect();
    }
    $service_cookie_val = substr($service_cookie_val, 0, $p);
    if (($i = strspn($service_cookie_val, "+-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz")) != $p) {
	cosign_debug("$service: Invalid character in service cookie $service_cookie_val at char $i");
	if ($rekey_service) return false;
	cosign_set_cookie_and_redirect();
    }

    // setup cookie cache directory
    $service_cookie_file = $service_cookie."=".$service_cookie_val;
    $dir = $cosign_cfg['CosignFilterDB'];
    if (!empty($cosign_cfg['CosignFilterHashLength'])) {
	if ($cosign_cfg['CosignFilterHashLength'] == 1) {
	    $dir .= $service_cookie_val[0]."/";
	} else {
	    $dir .= $service_cookie_val[0]."/".$service_cookie_val[1]."/";
	}
    } 

    $newfile = true;
    // cookie file exists?
    if (!$rekey_service && file_exists($dir.$service_cookie_file)) {
	$newfile = false;
	cosign_debug("$service: Service cookie file exists $service_cookie_file");
	$ts = filemtime($dir.$service_cookie_file);
	// read cookie file
	$fh = @fopen($dir.$service_cookie_file, "r");
	if ($fh === false) {
	    cosign_debug("$service: Cannot read cookie file $service_cookie_file");
	    $ts = 0;    // skip to file expiration
	} else {
	    $cf = array();
	    while (($line = fgets($fh)) !== false) {
		switch ($line[0]) {
		case "i": 
		case "p":
		case "r":
		case "k":
		case "f":
		    $cf[$line[0]] = trim(substr($line, 1));
		    break;
		default:
		}
	    }
	    fclose($fh);
	}

	// cookie file still valid?
	if ($ts + IDLE_TIME >= time()) {
	    // check client IP address and factors
	    if ($cosign_cfg['CosignCheckIP'] == 'always' &&
		!(strpos($_SERVER['REMOTE_ADDR'], ':') !== false &&
		strpos($cf['i'], ':') === false ||
		strpos($_SERVER['REMOTE_ADDR'], ':') === false &&
		strpos($cf['i'], ':') !== false ||
		strcasecmp($cf['i'], $_SERVER['REMOTE_ADDR']) == 0)) {
		cosign_debug("$service: IP address changed from {$cf['i']} to {$_SERVER['REMOTE_ADDR']}, user {$cf['p']}");
		// falout to cosign netcheck
	    } else
	    if (!empty($cosign_cfg['CosignRequireFactor']) &&
		!cosign_check_factors($cf['f'])) {
		// falout to cosign netcheck
	    } else {
		cosign_debug("$service: Service cookie file valid");
		$_SERVER["REMOTE_REALM"] = $cf['r'];
		$_SERVER["REMOTE_USER"] = $cf['p'];
		$_SERVER["AUTH_TYPE"] = "Cosign";
		$_SERVER["COSIGN_SERVICE"] = $cosign_cfg["CosignService"];
		$_SERVER["COSIGN_FACTOR"] = str_replace(' ', ',', $cf['f']);
		if (isset($cf['k'])) $_SERVER['KRB5CCNAME'] = $cf['k'];
		// flush output buffer if started in our script
		if ($obstart) ob_end_flush();
		if (is_resource($cosign_log)) fclose($cosign_log);
		error_reporting($level);
		return true;
	    }
	} else {
	    // cookie file expired
	    cosign_debug("$service: Service cookie file expired, revalidate it");
	}
    } 

    // no valid service cookie file and service cookie set
    $context = stream_context_create(array('ssl'=>array('local_cert'=> $cosign_cfg['CosignCryptoLocalCert'], 'capture_peer_cert'=>TRUE, 'capture_peer_chain'=>TRUE)));
    stream_context_set_option($context, 'ssl', 'peer_name', $cosign_cfg['CosignHostname']);
    if (isset($cosign_cfg['CosignCryptoVerifyPeer'])) {
	stream_context_set_option($context, 'ssl', 'verify_peer', $cosign_cfg['CosignCryptoVerifyPeer']);
    }
    if (isset($cosign_cfg['CosignCryptoAllowSelfSigned'])) {
	stream_context_set_option($context, 'ssl', 'allow_self_signed', $cosign_cfg['CosignCryptoAllowSelfSigned']);
    }
    if (isset($cosign_cfg['CosignCryptoCAFile'])) {
	stream_context_set_option($context, 'ssl', 'cafile', $cosign_cfg['CosignCryptoCAFile']);
    }
    if (isset($cosign_cfg['CosignCryptoCAPath'])) {
	stream_context_set_option($context, 'ssl', 'capath', $cosign_cfg['CosignCryptoCAPath']);
    }
    if (($dns = dns_get_record($cosign_cfg['CosignHostname'], DNS_A)) === false) {
	cosign_debug("$service: Cosign server {$cosign_cfg['CosignHostname']} not found");
	return false;
    }
    $servers = count($dns);
    for ($i = 0; $i < $servers; $i++) {
	cosign_debug("$service: Connecting to cosign server {$dns[$i]['ip']}");
	if (($sock = @stream_socket_client('tcp://'.($dns[$i]['type'] == 'A'?$dns[$i]['ip']:'['.$dns[$i]['ip'].']').':'.$cosign_cfg['CosignPort'], $errno, $errstr, SOCKET_TIMEOUT, STREAM_CLIENT_CONNECT, $context)) === false) {       
	    cosign_debug("$service: Cosign connect {$dns[$i]['ip']} failed - $errstr ($errno)");
	    if ($i < $servers-1) continue;
	    return false;
	}
	stream_set_timeout($sock, SOCKET_TIMEOUT);
	// 220 2 Collaborative Web Single Sign-On [COSIGNv3 REKEY ...]
	$response = trim(stream_get_line($sock, 1024, "\r\n"));
	cosign_debug("$service: Server response: $response");
	if ($response === false || $response === '' || $response[0] != '2') {
	    cosign_debug("$service: Cosign connect failed - invalid response");
	    fclose($sock);
	    if ($i < $servers-1) continue;
	    return false;
	}
    }
    $code = explode(' ', $response);
    $proto = intval($code[1]);
    if ($proto < 2 &&	// cosign protocol version
	$cosign_cfg['CosignProtocolVersion'] >= 2) {
	cosign_debug("$service: Cosign server doesn't support protocol version >=2");
	fclose($sock);
	return false;
    }
    $sup_factors = $sup_rekey = false;
    if (count($code) > 6 && strncasecmp($code[6], '[COSIGNv', 8) == 0) { // v3 protocol
	$proto = intval(substr($code[6], 8));
	if ($proto < 3) {
	    cosign_debug("$service: Cosign server invalid protocol level $proto");
	    $proto = 2;
	}
	$i = 6;
	while (substr($code[$i], -1) != ']') {
	    $i++;
	    if ($i >= count($code)) {
		cosign_debug("$service: Cosign server capabilities missing end parenthesis");
		break;
	    }
	    if (strncasecmp($code[$i], "FACTORS", 7) == 0) $sup_factors = true;
	    else
	    if (strncasecmp($code[$i], "REKEY", 5) == 0) $sup_rekey = true;
	    else {
		cosign_debug("$service: Cosign server unknown capability {$code[$i]}");
	    }
	}
    } else {
	if ($proto >= 2) $sup_factors = true;
    }

    if ($proto >= 2) {
	cosign_debug("$service: Sending STARTTLS $proto request");
	fwrite($sock, "STARTTLS $proto\r\n");
    } else {
	cosign_debug("$service: Sending STARTTLS request");
	fwrite($sock, "STARTTLS\r\n");
    }
    $response = trim(stream_get_line($sock, 1024, "\r\n"));
    cosign_debug("$service: Server response: $response");
    if ($response === false || $response === '' || $response[0] != '2') {
	cosign_debug("$service: Cosign server STARTTLS failed");
	fclose($sock);
	return false;
    }

    if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
	$last_err = error_get_last();
	cosign_debug("$service: stream_socket_enable_crypto error {$last_err['message']}");
	fclose($sock);
	return false;
    }

    // set nonblocking reading from stream for protocol v0
    if ($proto <= 1) {
	stream_set_blocking($sock, FALSE); 
	stream_set_timeout($sock, 1);  // 1 second
    }
    $response=trim(stream_get_line($sock, 1024, "\r\n"));
    cosign_debug("$service: Server response: $response");
    if ($response === false || $response === '' || $response[0] != '2') {
	cosign_debug("$service: Cosign server STARTTLS failed2");
	fclose($sock);
	return false;
    }
    if ($proto <= 1) {
	stream_set_blocking($sock, TRUE);
	stream_set_timeout($sock, SOCKET_TIMEOUT);
    }

    // check peer certificate
    $opts = stream_context_get_options($sock);
    if (!isset($opts['ssl']['peer_certificate'])) {
	cosign_debug("$service: No cosign server certificate returned");
	fclose($sock);
	return false;
    }
    $cert = openssl_x509_parse($opts['ssl']['peer_certificate']);
    if ($cert === false ||
	!isset($cert['subject']['CN']) ||
	$cert['subject']['CN'] != $cosign_cfg['CosignHostname']) {
	cosign_debug("$service: Cosign server certificate CN don't match");
	fclose($sock);
	return false;
    }

    // send check request
    if ($rekey_service) {
	if (!$sup_rekey) {
	    cosign_debug("$service: Cosign server doesn't support REKEY");
	    fclose($sock);
	    return false;
	}
	$cmd = "REKEY";
    } else $cmd = "CHECK";
    cosign_debug("$service: Sending $cmd request");
    fwrite($sock, "$cmd ".$service_cookie_file."\r\n");
    $response = trim(stream_get_line($sock, 1024, "\r\n"));
    cosign_debug("$service: Server response: $response");
    if ($response === false || $response === '' || $response[0] != '2' && $response[0] != '4' && $response[0] != '5') {
	cosign_debug("$service: Cosign server CHECK failed - invalid response");
	fclose($sock);
	return false;
    }
    if ($response[0] == '5') {  // retry later
	cosign_debug("$service: Cosign server CHECK failed - retry");
	fclose($sock);
	cosign_set_cookie_and_redirect($dest);
    }
    if ($response[0] == '4') {  // logout
	cosign_debug("$service: Cosign server CHECK failed - logged out");
	fclose($sock);
	cosign_set_cookie_and_redirect($dest);
    }
    // XXX proxy
    cosign_debug("$service: Response from cosign server - cookie valid");
    $code = explode(' ', $response);
    if ($rekey_service) {
	$i = count($code)-1;
	if (strncmp($code[$i], $service_cookie."=", strlen($service_cookie)+1) != 0) {
	    cosign_debug("CosignValid: missing rekeyed cookie {$code[$i]}");
	    fclose($sock);
	    return false;
	}
	$service_cookie_file = $code[$i];
	$service_cookie_val = substr($code[$i], strlen($service_cookie)+1);
	unset($code[$i]);
	if (!empty($cosign_cfg['CosignFilterHashLength'])) {
	    if ($cosign_cfg['CosignFilterHashLength'] == 1) {
		$dir = $cosign_cfg['CosignFilterDB'].$service_cookie_val[0]."/";
	    } else {
		$dir = $cosign_cfg['CosignFilterDB'].$service_cookie_val[0]."/".$service_cookie_val[1]."/";
	    }
	} 
    }

    if (!$newfile &&
	$cf['i'] != $code[1]) {
	cosign_debug("$service: ip address changed from {$cf['i']} to {$code[1]}");
	$newfile = true;
    }
    if (!$newfile &&
	$cf['p'] != $code[2]) {
	cosign_debug("$service: user name changed from {$cf['p']} to {$code[2]}");
	fclose($sock);
	return false;
    }
    if (!$newfile &&
	$cf['r'] != $code[3]) {
	cosign_debug("$service: realm changed from {$cf['r']} to {$code[3]}");
	fclose($sock);
	return false;
    }
    $cf['i'] = $code[1];
    $cf['p'] = $code[2];
    $cf['r'] = $code[3];
    if ($sup_factors) {
	unset($code[0]);
	unset($code[1]);
	unset($code[2]);
	$factors = implode(' ', $code);
	if (!$newfile && 
	    $cf['f'] != $factors) {
	    $cosign_debug("$service: factors changed from {$cf['f']} to $factors");
	    $newfile = true;
	}
	$cf['f'] = $factors;
    } else {
	$cf['f'] = '';
    }

    // check client IP address
    if (($cosign_cfg['CosignCheckIP'] == 'always' ||
	$newfile && $cosign_cfg['CosignCheckIP'] == 'initial') &&
	!(strpos($_SERVER['REMOTE_ADDR'], ':') !== false &&
	strpos($cf['i'], ':') === false ||
	strpos($_SERVER['REMOTE_ADDR'], ':') === false &&
	strpos($cf['i'], ':') !== false ||
	strcasecmp($cf['i'], $_SERVER['REMOTE_ADDR']) == 0)) {
	cosign_debug("$service: IP address changed from {$cf['i']} to {$_SERVER['REMOTE_ADDR']}, user {$cf['p']}");
	fclose($sock);
	cosign_set_cookie_and_redirect($dest);
    }
    if (!empty($cosign_cfg['CosignRequireFactor']) &&
	!cosign_check_factors($cf['f'], true)) {
	fclose($sock);
	if (!$sup_factors) {
	    cosign_debug("$service: Server doesn't support required factors");
	    return false;
	}
	// try again?
	cosign_set_cookie_and_redirect();
    }
    if ($newfile) {
	if (!empty($cosign_cfg['CosignGetKerberosTickets'])) {
	    cosign_debug("$service: Sending RETR request");
	    fwrite($sock, "RETR ".$service_cookie_file." tgt\r\n");
	    $response = trim(stream_get_line($sock, 1024, "\r\n"));
	    cosign_debug("$service: Server response: $response");
	    if ($response === false || $response === '' || $response[0] != '2') {
		cosign_debug("$service: Cosign server RETR failed");
		fclose($sock);
		if ($rekey_service) return false;
		cosign_set_cookie_and_redirect();
	    }
	    $response = trim(stream_get_line($sock, 1024, "\r\n"));
	    cosign_debug("$service: Server response: $response");
	    if ($response === false || $response === '' || !intval($response)) {
		cosign_debug("$service: Cosign server RETR failed2");
		fclose($sock);
		if ($rekey_service) return false;
		cosign_set_cookie_and_redirect();
	    }
	    $sz = intval($response);
	    $chars = "+-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
	    $krbf = '';
	    for ($i = 0; $i < 64; $i++) {  // XX??
		$krbf .= $chars[mt_rand(0, 63)];
	    }
	    $kf = @fopen($cosign_cfg['CosignTicketPrefix'].$krbf, "w");
	    while ($sz > 0) {
		$response = fread($sock, $sz);
		if ($response === false) {
		    cosign_debug("$service: Cosign server RETR failed3");
		    fclose($sock);
		    if ($rekey_service) return false;
		    cosign_set_cookie_and_redirect();
		}
		fwrite($kf, $response);
		$sz -= strlen($response);
	    }
	    fclose($kf);
	    $response = trim(stream_get_line($sock, 1024, "\r\n"));
	    cosign_debug("$service: Server response: $response");
	    if ($response === false || $response === '' || $response[0] != '.') {
		cosign_debug("$service: Cosign server RETR failed4");
		fclose($sock);
		if ($rekey_service) return false;
		cosign_set_cookie_and_redirect();
	    }
	    $cf['k'] = $cosign_cfg['CosignTicketPrefix'].$krbf;
	} 
	if (!empty($cosign_cfg['CosignFilterHashLength'])) {
	    if ($cosign_cfg['CosignFilterHashLength'] != 1) {
		$dir2 = basename($dir);
		if (!is_dir($dir2)) mkdir($dir2);
	    }
	    if (!is_dir($dir)) mkdir($dir);
	}
	$fn = tempnam($dir, "cosign");
	$fh = @fopen($fn, "w");
	if ($fh === false) {
	    cosign_debug("$service: Cannot create cookie file $fn");
	    fclose($sock);
	    return false;
	}
	fwrite($fh, "i".$cf['i']."\n");
	fwrite($fh, "p".$cf['p']."\n");
	fwrite($fh, "r".$cf['r']."\n");
	fwrite($fh, "f".$cf['f']."\n");
	if (isset($cf['k'])) fwrite($fh, "k".$cf['k']."\n");
	fclose($fh);
	if (!@rename($fn, $dir.$service_cookie_file)) {
	    cosign_debug("$service: Cannot rename cookie file $fn to $dir.$service_cookie_file");
	}
    } else {    // update cookie file timestamp
	@touch($dir.$service_cookie_file);
    }
    fclose($sock);
    if ($rekey_service) {
	cosign_debug("CosignValid: Setting new $service_cookie cookie $service_cookie_val and redirecting to $dest");
	if (is_resource($cosign_log)) fclose($cosign_log);
	ob_end_clean();
	setrawcookie($service_cookie, $service_cookie_val."/".time(), 0, '/', '', (strncmp($dest, 'https://', 8) == 0?TRUE:FALSE), TRUE);
	header("Location: $dest");
	exit();
    }
	
    $_SERVER["REMOTE_REALM"] = $cf['r'];
    $_SERVER["REMOTE_USER"] = $cf['p'];
    $_SERVER["AUTH_TYPE"] = "Cosign";
    $_SERVER["COSIGN_SERVICE"] = $cosign_cfg["CosignService"];
    $_SERVER["COSIGN_FACTOR"] = str_replace(' ', ',', $cf['f']);
    if (isset($cf['k'])) $_SERVER['KRB5CCNAME'] = $cf['k'];
    // flush output buffer
    if ($obstart) ob_end_flush();
    if (is_resource($cosign_log)) fclose($cosign_log);
    error_reporting($level);
    return true;
}

function cosign_set_cookie_and_redirect($url = '')
{
    global $cosign_cfg;
    global $cosign_log;

    if ($url) {
	cosign_debug("CosignFilter: REKEY failed, retry $url");
    // check POST request expiration                     
    } else
    if (strcasecmp($_SERVER['REQUEST_METHOD'], "post") == 0 &&
	isset($cosign_cfg['CosignPostErrorRedirect'])) {
	cosign_debug("CosignFilter: Cookie not valid and POST request");
	$url = $cosign_cfg['CosignPostErrorRedirect'];
    } else {
	if (!empty($cosign_cfg['CosignSiteEntry']) &&
	    $cosign_cfg['CosignSiteEntry'] != 'none') {
	    $back = $cosign_cfg['CosignSiteEntry'];
	} else {
	    if (!empty($cosign_cfg['CosignHTTPOnly'])) {
		$back = "http://" . $_SERVER['SERVER_NAME'];
		if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) $back .= ":".$_SERVER['SERVER_PORT'];
	    } else {
		$back = "https://" . $_SERVER['SERVER_NAME'];
	    }
	    $back .= $_SERVER['REQUEST_URI'];
	}
	if ($cosign_cfg['CosignProtocolVersion'] <= 2) {
	    cosign_debug("CosignFilter: Setting new cookie and redirect to login");
	    $chars = "+-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
	    $service_cookie_val = '';
	    for ($i = 0; $i < 124; $i++) {  // XX??
		$service_cookie_val .= $chars[mt_rand(0, 63)];
	    }
	    $service_cookie = "cosign-".$cosign_cfg["CosignService"];
	    $service_cookie_file = $service_cookie."=".$service_cookie_val;
	    cosign_debug("CosignFilter: New cookie $service_cookie_file");
	    setrawcookie($service_cookie, $service_cookie_val."/".time(), 0, '/', '', (strncmp($back, 'https://', 8) == 0?TRUE:FALSE), TRUE);
	} else {
	    cosign_debug("CosignFilter: Redirect to login");
	    $service_cookie_file = "cosign-".$cosign_cfg["CosignService"];
	}
	if (!empty($cosign_cfg['CosignRequireFactor'])) {
	    $factors = str_replace(" ", ",", $cosign_cfg['CosignRequireFactor']);
	    if ($factors !== "") $factors="factors=".$factors."&";
	} else $factors = '';
	$url = $cosign_cfg['CosignRedirect']."?".$factors.$service_cookie_file."&".$back;
    }
    if (is_resource($cosign_log)) fclose($cosign_log);
    ob_end_clean();
    header("Location: ".$url);
    // clean output buffer
    exit();
}

function cosign_check_factors($fa, $suffix = false)
{
    global $cosign_cfg;
    $req_fac = explode(' ', $cosign_cfg['CosignRequireFactor']);
    $sc_fac = explode(' ', $fa);
    foreach ($req_fac as $rf) {
	if (in_array($rf, $sc_fac)) continue;
	if ($suffix && isset($cosign_cfg['CosignFactorSuffix']) &&
	    in_array($rf.$cosign_cfg['CosignFactorSuffix'], $sc_fac)) {
	    if (empty($cosign_cfg['CosignFactorSuffixIgnore'])) continue;
	    cosign_debug("CosignFilter: factor $rf matches with suffix, but suffix matching is OFF");
	}
	cosign_debug("CosignFilter: Required factor $rf missing in service cookie file");
	return false;
    }
    return true;
}
