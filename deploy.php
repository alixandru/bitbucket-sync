<?php

/*
	BitBucket Sync (c) Alex Lixandru

	https://bitbucket.org/alixandru/bitbucket-sync

	File: deploy.php
	Version: 2.0.0
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
	This script has two modes of operation detailed below.
	
	The two modes of operation are complementary and are designed to be used
	with projects that are configured to be kept in sync through this script. 
	
	The usual way of getting the project prepared is to make an initial full 
	sync of the	project files (through operation mode 2) and then to configure
	the POST service hook in BitBucket and let the script synchronize changes 
	as they happen (through operation mode 1).
	
	
	1. Full synchronization
	
	This mode can be enabled by specifying the "setup" GET parameter in the URL
	in which case the script will get the full repository from BitBucket and
	deploy it locally. This is done by getting a zip archive of the project,
	extracting it locally and copying its contents over to the specified
	project location, on the local file-system.
	
	This operation mode does not necessarily need a POST service hook to be 
	defined in BitBucket for the project and is generally suited for initial 
	set-up of projects that will be kept in sync with this script. 
	
	
	2. Commit synchronization
	
	This is the default mode which is used when the script is accessed with
	no parameters in the URL. In this mode, the script updates only the files
	which have been modified by a commit that was pushed to the repository.
	
	The script reads commit information saved locally by the gateway script
	and attempts to synchronize the local file system with the updates that
	have been made in the BitBucket project. The list of files which have
	been changed (added, updated or deleted) will be taken from the commit
	files. This script tries to optimize the synchronization by not processing 
	files more than once.
	
	When a deployment fails the original commit file is preserved. It is 
	possible to retry processing failed synchronizations by specifying the 
	"retry" GET parameter in the URL.
	
 */


ini_set('display_errors','On'); 
ini_set('error_reporting', E_ALL);
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

if (!isset($key)) {
	if(isset($_GET['key'])) {
		$key = strip_tags(stripslashes(urlencode($_GET['key'])));

	} else $key = '';
}

if(isset($_GET['setup']) && !empty($_GET['setup'])) {
	# full synchronization
	$repo = strip_tags(stripslashes(urldecode($_GET['setup'])));
	syncFull($key, $repo);
	
} else if(isset($_GET['retry'])) {
	# retry failed synchronizations
	syncChanges($key, true);
	
} else {
	# commit synchronization
	syncChanges($key);
}


/**
 * Gets the full content of the repository and stores it locally.
 * See explanation at the top of the file for details.
 */
function syncFull($key, $repository) {
	global $CONFIG, $DEPLOY, $DEPLOY_BRANCH;
	$shouldClean = isset($_GET['clean']) && $_GET['clean'] == 1;

	// check authentication key if authentication is required
	if ( $shouldClean && $CONFIG[ 'deployAuthKey' ] == '' ) {
		// when cleaning, the auth key is mandatory, regardless of requireAuthentication flag
		http_response_code(403);
		echo " # Cannot clean right now. A non-empty deploy auth key must be defined for cleaning.";
		return false;
	} else if ( ($CONFIG[ 'requireAuthentication' ] || $shouldClean) && $CONFIG[ 'deployAuthKey' ] != $key ) {
		http_response_code(401);
		echo " # Unauthorized." . ($shouldClean && empty($key) ? " The deploy auth key must be provided when cleaning." : "");
		return false;
	}
	
	echo "<pre>\nBitBucket Sync - Full Deploy\n============================\n";
	
	// determine the destination of the deployment
	if( array_key_exists($repository, $DEPLOY) ) {
		$deployLocation = $DEPLOY[ $repository ] . (substr($DEPLOY[ $repository ], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	} else {
		echo " # Unknown repository: $repository!";
		return false;
	}
	
	// determine from which branch to get the data
	if( isset($DEPLOY_BRANCH) && array_key_exists($repository, $DEPLOY_BRANCH) ) {
		$deployBranch = $DEPLOY_BRANCH[ $repository ];
	} else {
		// use the default branch
		$deployBranch = $CONFIG['deployBranch'];
	}

	// build URL to get the full archive
	$baseUrl = 'https://bitbucket.org/';
	$repoUrl = (!empty($_GET['team']) ? $_GET['team'] : $CONFIG['apiUser']) . "/$repository/";
	$branchUrl = 'get/' . $deployBranch . '.zip';
	
	// store the zip file temporary
	$zipFile = 'full-' . time() . '-' . rand(0, 100);
	$zipLocation = $CONFIG['commitsFolder'] . (substr($CONFIG['commitsFolder'], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);

	// get the archive
	loginfo(" * Fetching archive from $baseUrl$repoUrl$branchUrl\n");
	$result = getFileContents($baseUrl . $repoUrl . $branchUrl, $zipLocation . $zipFile);

	// extract contents
	loginfo(" * Extracting archive to $zipLocation\n");
	$zip = new ZipArchive;
	if( $zip->open($zipLocation . $zipFile) === true ) {
		$zip->extractTo($zipLocation);
		$stat = $zip->statIndex(0); 
		$folder = $stat['name'];
		$zip->close();
	} else {
		echo " # Unable to extract files. Is the repository name correct?";
		unlink($zipLocation . $zipFile);
		return false;
	}
	
	// validate extracted content
	if( empty($folder) || !is_dir( $zipLocation . $folder ) ) {
		echo " # Unable to find the extracted files in $zipLocation\n";
		unlink($zipLocation . $zipFile);
		return false;
	}
	
	// delete the old files, if instructed to do so
	if( $shouldClean ) {
		loginfo(" * Deleting old content from $deployLocation\n");
		if( deltree($deployLocation) === false ) {
			echo " # Unable to completely remove the old files from $deployLocation. Process will continue anyway!\n";
		}
	}
	
	// copy the contents over
	loginfo(" * Copying new content to $deployLocation\n");
	if( cptree($zipLocation . $folder, $deployLocation) == false ) {
		echo " # Unable to deploy the extracted files to $deployLocation. Deployment is incomplete!\n";
		deltree($zipLocation . $folder, true);
		unlink($zipLocation . $zipFile);
		return false;
	}
	
	// clean up
	loginfo(" * Cleaning up temporary files and folders\n");
	deltree($zipLocation . $folder, true);
	unlink($zipLocation . $zipFile);
	
	echo "\nFinished deploying $repository.\n</pre>";
}


/**
 * Synchronizes changes from the commit files.
 * See explanation at the top of the file for details.
 */
function syncChanges($key, $retry = false) {
	global $CONFIG;
	global $processed;
	global $rmdirs;
	
	// check authentication key if authentication is required
	if ( $CONFIG[ 'requireAuthentication' ] && $CONFIG[ 'deployAuthKey' ] != $key) {
		http_response_code(401);
		echo " # Unauthorized";
		return false;
	}

	echo "<pre>\nBitBucket Sync\n==============\n";
	
	$prefix = $CONFIG['commitsFilenamePrefix'];
	if($retry) {
		$prefix = "failed-$prefix";
	}
	
	$processed = array();
	$rmdirs = array();
	$location = $CONFIG['commitsFolder'] . (substr($CONFIG['commitsFolder'], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	$commits = @scandir($location, 0);

	if($commits)
	foreach($commits as $file) {
		if( $file != '.' && $file != '..' && is_file($location . $file) 
			&& stripos($file, $prefix) === 0 ) {
			// get file contents and parse it
			$json = @file_get_contents($location . $file);
			$del = true;
			echo " * Processing file $file\n";
			if(!$json || !deployChangeSet( $json )) {
				echo " # Could not process file $file!\n";
				$del = false;
			}
			flush();
			
			if($del) {
				// delete file afterwards
				unlink( $location . $file );
			} else {
				// keep failed file for later processing
				if(!$retry) rename( $location . $file, $location . 'failed-' . $file );
			}
		}
	}
	
	// remove old (renamed) directories which are empty
	foreach($rmdirs as $dir => $name) {
		if(@rmdir($dir)) {
			echo " * Removed empty directory $name\n";
		}
	}
	
	echo "\nFinished processing commits.\n</pre>";
}


/**
 * Deploys commits to the file-system
 */
function deployChangeSet( $postData ) {
	global $CONFIG, $DEPLOY, $DEPLOY_BRANCH;
	global $processed;
	global $rmdirs;
	
	$o = json_decode($postData);
	if( !$o ) {
		// could not parse ?
		echo "    ! Invalid JSON file\n";
		return false;
	}
	
	// determine the destination of the deployment
	if( array_key_exists($o->repository->slug, $DEPLOY) ) {
		$deployLocation = $DEPLOY[ $o->repository->slug ] . (substr($DEPLOY[ $o->repository->slug ], -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	} else {
		// unknown repository ?
		echo "    ! Repository not configured for sync: {$o->repository->slug}\n";
		return false;
	}
	
	// determine from which branch to get the data
	if( isset($DEPLOY_BRANCH) && array_key_exists($o->repository->slug, $DEPLOY_BRANCH) ) {
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
		// check if the branch is known at this step
		loginfo("    > Change-set: " . trim($commit->message) . "\n");
		if(!empty($commit->branch) || !empty($commit->branches)) {
			// if commit was on the branch we're watching, deploy changes
			if( $commit->branch == $deployBranch || 
					(!empty($commit->branches) && array_search($deployBranch, $commit->branches) !== false)) {
				// if there are any pending files, merge them in
				$files = array_merge($pending, $commit->files);
				
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
							
							if( $contents != 'Not Found' && $contents !== false ) {
								if( !is_dir( dirname($deployLocation . $file->file) ) ) {
									// attempt to create the directory structure first
									mkdir( dirname($deployLocation . $file->file), 0755, true );
								}
								file_put_contents( $deployLocation . $file->file, $contents );
								loginfo("      - Synchronized $file->file\n");
								
							} else {
								echo "      ! Could not get file contents for $file->file\n";
								flush();
							}
						}
						
					} else if( $file->type == 'removed' ) {
						unlink( $deployLocation . $file->file );
						$processed[$file->file] = 0; // to allow for subsequent re-creating of this file
						$rmdirs[dirname($deployLocation . $file->file)] = dirname($file->file);
						loginfo("      - Removed $file->file\n");
					}
				}
			}
			
			// clean pending files, if any
			$pending = array();
		
		} else {
			// unknown branch for now, keep these files
			$pending = array_merge($pending, $commit->files);
		}
	}
	
	return true;
}


/**
 * Gets a remote file contents using CURL
 */
function getFileContents($url, $writeToFile = false) {
	global $CONFIG;
	
	// create a new cURL resource
	$ch = curl_init();
	
	// set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, $url);
	
	curl_setopt($ch, CURLOPT_HEADER, false);

    if ($writeToFile) {
        $out = fopen($writeToFile, "wb");
        if ($out == FALSE) {
            throw new Exception("Could not open file `$writeToFile` for writing");
        }
        curl_setopt($ch, CURLOPT_FILE, $out);
    } else {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    }

	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
	
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
	if(!empty($CONFIG['apiUser'])) {
		curl_setopt($ch, CURLOPT_USERPWD, $CONFIG['apiUser'] . ':' . $CONFIG['apiPassword']);
	}
	// Remove to leave curl choose the best version
	//curl_setopt($ch, CURLOPT_SSLVERSION,3); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	
	// grab URL
	$data = curl_exec($ch);
	
	if(curl_errno($ch) != 0) {
		echo "      ! File transfer error: " . curl_error($ch) . "\n";
	}
	
	// close cURL resource, and free up system resources
	curl_close($ch);
	
	return $data;
}


/**
 * Copies the directory contents, recursively, to the specified location
 */
function cptree($dir, $dst) {
	if (!file_exists($dst)) if(!mkdir($dst, 0755, true)) return false;
	if (!is_dir($dir) || is_link($dir)) return copy($dir, $dst); // should not happen
	$files = array_diff(scandir($dir), array('.','..'));
	$sep = (substr($dir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	$dsp = (substr($dst, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	foreach ($files as $file) {
		(is_dir("$dir$sep$file")) ? cptree("$dir$sep$file", "$dst$dsp$file") : copy("$dir$sep$file", "$dst$dsp$file");
	}
	return true;
}


/**
 * Deletes a directory recursively, no matter whether it is empty or not
 */
function deltree($dir, $deleteParent = false) {
	if (!file_exists($dir)) return false;
	if (!is_dir($dir) || is_link($dir)) return unlink($dir);
	// prevent deletion of current directory
	$cdir = realpath($dir);
	$adir = dirname(__FILE__);
	$cdir = $cdir . (substr($cdir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	$adir = $adir . (substr($adir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	if( $cdir == $adir ) {
		loginfo(" * Contents of '" . basename($adir) . "' folder will not be cleaned up.\n");
		return true;
	}
	// process contents of this dir
	$files = array_diff(scandir($dir), array('.','..'));
	$sep = (substr($dir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
	foreach ($files as $file) {
		(is_dir("$dir$sep$file")) ? deltree("$dir$sep$file", true) : unlink("$dir$sep$file");
	}

	if($deleteParent) {
		return rmdir($dir);
	} else {
		return true;
	}
}


/**
 * Outputs some information to the screen if verbose mode is enabled
 */
function loginfo($message) {
	global $CONFIG;
	if( $CONFIG['verbose'] ) {
		echo $message;
		flush();
	}
}
