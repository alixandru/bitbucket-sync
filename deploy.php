<?php

/*
	BitBucket Sync (c) Alex Lixandru

	https://bitbucket.org/alixandru/bitbucket-sync

	File: deploy.php
	Version: 1.0.0
	Description: Local file sync script for BitBucket projects


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
	This script reads commit information saved locally by the gateway script
	and attempts to synchronize the local file system with the updates that
	have been made in the BitBucket project. The list of files which have
	been changed (added, updated or deleted) will be taken from the commit
	files. This script tries to optimize the synchronization by not processing 
	files more than once.
 */

ini_set('display_errors','On'); 
ini_set('error_reporting', E_ALL);
require_once( 'config.php' );

echo "<pre>BitBucket Sync\n===============\n";


$processed = array();
$location = $CONFIG['commitsFolder'] . (substr($CONFIG['commitsFolder'], -1) == '/' ? '' : '/');
$commits = @scandir($location, 0);

if($commits)
foreach($commits as $file) {
	if( $file != '.' && $file != '..' && is_file($location . $file) 
		&& stristr($file, $CONFIG['commitsFilenamePrefix']) !== false ) {
		// get file contents and parse it
		$json = @file_get_contents($location . $file);
		if(!$json || !deployChangeSet( $json )) {
			echo " # Could not process changeset!\n$json\n\n";
		} else {
			echo " * Processed file $file\n";
		}
		
		// delete file afterwards
		unlink( $location . $file );
	}
}
echo "\nFinished processing commits.</pre>";


/**
 * Deploys commits to the file-system
 */
function deployChangeSet( $postData ) {
	global $CONFIG, $DEPLOY, $DEPLOY_BRANCH;
	global $processed;
	
	$o = json_decode($postData);
	if( !$o ) {
		// could not parse ?
		return false;
	}
	
	// determine the destination of the deployment
	if( array_key_exists($o->repository->slug, $DEPLOY) ) {
		$deployLocation = $DEPLOY[ $o->repository->slug ] . (substr($DEPLOY[ $o->repository->slug ], -1) == '/' ? '' : '/');
	} else {
		// unknown repository ?
		return false;
	}
	
	// determine from which branch to get the data
	if( array_key_exists($o->repository->slug, $DEPLOY_BRANCH) ) {
		$deployBranch = $DEPLOY_BRANCH[ $o->repository->slug ];
	} else {
		// use the default branch
		$deployBranch = $CONFIG['deployBranch'];
	}
	
	// build URL to get the updated files
	$baseUrl = $o->canon_url;                       # https://bitbucket.org
	$apiUrl = '/api/1.0/repositories';              # /api/1.0/repositories
	$repoUrl = $o->repository->absolute_url;        # /user/repo/
	$rawUrl = 'raw/';								# raw/
	$branchUrl = $deployBranch . '/';     			# branch/
	
	// prepare to get the files
	$pending = array();
	
	// loop through commits
	foreach($o->commits as $commit) {
		// if commit was on the branch we're watching, deploy changes
		if( $commit->branch == $deployBranch ) {
			// if there are any pending files, merge them in
			$files = array_merge($pending, $commit->files);
			
			// clean pending, if any
			$pending = array();
			
			// get a list of files
			foreach($files as $file) {
				if( $file->type == 'modified' || $file->type == 'added' ) {
					if( empty($processed[$file->file]) ) {
						$processed[$file->file] = 1; // mark as processed
						$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file->file);
						if( $contents == 'Not Found' ) {
							// try one more time, BitBucket gets weirdo sometimes
							$contents = getFileContents($baseUrl . $apiUrl . $repoUrl . $rawUrl . $branchUrl . $file->file);
						}
						
						if( $contents != 'Not Found' ) {
							if( !is_dir( dirname($deployLocation . $file->file) ) ) {
								// attempt to create the directory structure first
								mkdir( dirname($deployLocation . $file->file), 0755, true );
							}
							file_put_contents( $deployLocation . $file->file, $contents );
						} else {
							echo "Could not get file contents for $file->file\n";
						}
					}
					
				} else if( $file->type == 'removed' ) {
					unlink( $deployLocation . $file->file );
					$processed[$file->file] = 0; // to allow for subsequent re-creating of this file
				}
			}
		} else if(empty($commit->branch) && empty($commit->branches)) {
			// unknown branch for now, keep these files
			$pending = array_merge($pending, $commit->files);
		}
	}
	
	return true;
}


/**
 * Gets a remote file contents using CURL
 */
function getFileContents($url) {
	global $CONFIG;
	
	// create a new cURL resource
	$ch = curl_init();
	
	// set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, $url);
	
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
	
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
	if(!empty($CONFIG['apiUser'])) {
		curl_setopt($ch, CURLOPT_USERPWD, $CONFIG['apiUser'] . ':' . $CONFIG['apiPassword']);
	}
	curl_setopt($ch, CURLOPT_SSLVERSION,3);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	
	// grab URL
	$data = curl_exec($ch);
	
	// close cURL resource, and free up system resources
	curl_close($ch);
	
	return $data;
}

