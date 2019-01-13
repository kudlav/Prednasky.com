<?php
// Copyright (C) 2010 FIT Brno University of Technology
// All Rights Reserved. See LICENSE.
// Petr Lampa <lampa@fit.vutbr.cz>
// $Id: cosign_config.php,v 1.1 2010/03/01 10:32:16 lampa Exp $

// Enable Cosign Authentication
$cosign_cfg['CosignProtected'] = true;

// Hostname of server running cosignd
$cosign_cfg['CosignHostname'] = 'cas.fit.vutbr.cz';

// The port on which cosignd listens
$cosign_cfg['CosignPort'] = '6663';

// The name of cosign service cookie
$cosign_cfg['CosignService'] = 'FIT-prednasky';

// The URL to redirect for login
$cosign_cfg['CosignRedirect'] = 'https://cas.fit.vutbr.cz';

// Filter DB directory. Must end with trailing slash
$cosign_cfg['CosignFilterDB'] = '/domains/prednasky.com/cosign/filter/';

// Expiration time of service cookie in seconds
$cosign_cfg['CosignCookieExpireTime'] = 3600*24;

// Debug log file path
$cosign_cfg['CosignFilterLog'] = '/domains/prednasky.com/cosign/logs/cosign-filter.log';

// Enable debug log (boolean)
$cosign_cfg['CosignFilterDebug'] = true;

// Version of Cosign protocol
$cosign_cfg['CosignProtocolVersion'] = 3;

// The URL to which a user is redirected to if an error is
// encountered during a POST
$cosign_cfg['CosignPostErrorRedirect'] = 'https://cas.fit.vutbr.cz/validation_error.html';

// A list space separated factors that must be satisfied by the user
$cosign_cfg['CosignRequireFactor'] = '';

// Suffix, that is ignored in cosign factors
$cosign_cfg['CosignFactorSuffix'] = '-junk';

// Toggles, whether the value of CosignFactorSuffix is ignored
$cosign_cfg['CosignFactorSuffixIgnore'] = false;

// URL to which the user is redirected after login
$cosign_cfg['CosignSiteEntry'] = 'https://prednasky.com/sign/in';

// Use only http protocol to redirect back after login
$cosign_cfg['CosignHTTPOnly'] = false;

// Verify browser's IP against cosignd's IP information (no/initial/always)
$cosign_cfg['CosignCheckIP'] = 'initial';

// Subdirectory hash length (0,1,2) for Cosign filter cookie file storage
$cosign_cfg['CosignFilterHashLength'] = 0;

// Toggles whether proxy cookies will be requested from cosignd
$cosign_cfg['CosignGetProxyCookies'] = false;

// Cosign filter proxy DB directory. Must end with trailing slash
// NOT IMPLEMENTED
$cosign_cfg['CosignProxyDB'] = '/domains/prednasky.com/cosign/proxy/';

/*
** SSL context directives
*/

// PEM encoded certificate and private key
$cosign_cfg['CosignCryptoLocalCert'] = '/domains/prednasky.com/cosign/certs/prednasky.pem';

// Passphrase for private key (if private key is protected)
$cosign_cfg['CosignCryptoPassphrase'] = '';

// Require verification of server certificate
$cosign_cfg['CosignCryptoVerifyPeer'] = 1;

// Allow self-signed certificates
$cosign_cfg['CosignCryptoAllowSelfSigned'] = false;

// CA certificate which should be used to verify server certificate
$cosign_cfg['CosignCryptoCAFile'] = '/domains/prednasky.com/cosign/certs/CAcertificate.pem';

// CA certificates directory (must be a correctly hashed certificate directory)
$cosign_cfg['CosignCryptoCAPath'] = '/domains/prednasky.com/cosign/certs/CA';

/*
** Kerberos directives section
*/

//  Toggles whether the value of TGT will be requested from cosignd
$cosign_cfg['CosignGetKerberosTickets'] = false;

// Kerberos ticket filter DB directory. Must end with trailing slash
$cosign_cfg['CosignTicketPrefix'] = '/domains/prednasky.com/cosign/tickets/';
