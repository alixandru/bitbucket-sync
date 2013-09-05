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

$file = $CONFIG['commitsFilenamePrefix'] . time() . '-' . rand(0, 100);
$location = $CONFIG['commitsFolder'] . (substr($CONFIG['commitsFolder'], -1) == '/' ? '' : '/');

if(!empty($_POST['payload'])) {
	// store commit data
	file_put_contents( $location . $file, stripslashes(urldecode($_POST['payload'])));
	
	// process the commit data right away
	if($CONFIG['automaticDeployment']) {
		require_once( 'deploy.php' );
	}
}


/* Omit PHP closing tag to help avoid accidental output */
