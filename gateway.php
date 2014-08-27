<?php

/*
	BitBucket Sync (c) Alex Lixandru

	https://bitbucket.org/alixandru/bitbucket-sync

	File: gateway.php
	Version: 1.0.0
	Description: Service hook handler for BitBucket projects


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
*/


/*
	This script accepts commit information from BitBucket. Commit information 
	is automatically posted by BitBucket after each push to a repository, 
	through its Post Service Hook. For details on how to setup a service hook, 
	see https://confluence.atlassian.com/display/BITBUCKET/POST+hook+management
 */

require_once( 'config.php' );

// For 4.3.0 <= PHP <= 5.4.0
if (!function_exists('http_response_code'))
{
    function http_response_code($newcode = NULL)
    {
        static $code = 200;
        if($newcode !== NULL)
        {
            header('X-PHP-Response-Code: '.$newcode, true, $newcode);
            if(!headers_sent())
                $code = $newcode;
        }       
        return $code;
    }
}

$file = $CONFIG['commitsFilenamePrefix'] . time() . '-' . rand(0, 100);
$location = $CONFIG['commitsFolder'] . (substr($CONFIG['commitsFolder'], -1) == '/' ? '' : '/');

// Parse auhentication key from request
if(isset($_GET['key'])) {
	$key = strip_tags(stripslashes(urlencode($_GET['key'])));

} else $key = '';

// check authentication key if authentication is required
if ( !$CONFIG['requireAuthentication'] || $CONFIG[ 'requireAuthentication' ] && $CONFIG[ 'gatewayAuthKey' ] == $key) {
	if(!empty($_POST['payload'])) {
		// store commit data
		if (get_magic_quotes_gpc()) {
			file_put_contents( $location . $file, stripslashes($_POST['payload']));
		} else {
			file_put_contents( $location . $file, $_POST['payload']);
		}
		
		// process the commit data right away
		if($CONFIG['automaticDeployment']) {
				$key = $CONFIG['deployAuthKey'];
				require_once( 'deploy.php' );
		}
		
	} else if(isset($_GET['test'])) {
		if(file_put_contents( $location . 'test', 'Files can be created by the gateway script.') === false) {
			echo "This script does not have access to create files in $location";
		} else {
			echo "Files can be created by this script in $location";
		}
	}
}
else http_response_code(401);

/* Omit PHP closing tag to help avoid accidental output */
