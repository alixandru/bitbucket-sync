<?php

/*
	BitBucket Sync (c) Alex Lixandru

	https://bitbucket.org/alixandru/bitbucket-sync

	File: config.php
	Version: 2.0.0
	Description: Configuration file for BitBucket Sync script


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
*/



/** Configuration for BitBucket Sync. */
$CONFIG = array(
	
	/** 
	 * The location where to temporary store commit data sent by BitBucket's 
	 * Post Service hook. This is the location from where the deploy script 
	 * will read information about what files to synchronize. The process 
	 * executing both the gateway script and the deploy script (usually a web 
	 * server daemon), must have read and write access to this folder.
	 */
	'commitsFolder' => 'commits',
	
	
	/**
	 * Prefix of the temporary files created by the gateway script. This prefix
	 * will be used to identify the files from `commitsFolder` which will be
	 * used to extract commit information.
	 */
	'commitsFilenamePrefix' => 'commit-',
	
	
	/**
	 * Whether to perform the file synchronization automatically, immediately 
	 * after the Post Service hook is triggered, or to skip it, leaving it for
	 * manual deployment request.
	 */
	'automaticDeployment' => false,
	
	
	/**
	 * The default branch to use for getting the changed files, if no specific
	 * per-project branch was configured below.
	 */
	'deployBranch' => 'master',
	
	
	/** The ID of an user with read access to project files */
	'apiUser' => '',
	
	
	/** The password of [apiUser] account */
	'apiPassword' => '',
	
	
	/** Whether to print operation details. Very useful, especially when setting up projects */
	'verbose' => true,
	
	/** Whether to require authentication key parameter when requesting gateway or deploy scripts */
	'requireAuthentication' => false,
	
	/** 
	 * Authentication key value. This value needs to be given in the "key" URL parameter in requests to gateway
	 * script if requireAuthentication config parameter is set to true.
	 */
	'gatewayAuthKey' => '',
	
	/** 
	 * Authentication key value. This value needs to be given in the "key" URL parameter in requests to deploy
	 * script if requireAuthentication config parameter is set to true.
	 */
	'deployAuthKey' => '',

);


/** 
 * The location where the project files will be deployed when modified in the
 * BitBucket project, identified by the name of the BitBucket project. The 
 * following pattern is used: [project-name] => [path on the web-server].
 * This allows multiple BitBucket projects to be deployed to different 
 * locations on the web-server's file system.
 */
$DEPLOY = array(
	'my-repo' => '/home/www/data/',
	'cool-site' => '/home/www/site/',
	'user.bitbucket.org' => '/home/www/bbpages/',
);


/** 
 * The branch which will be deployed for each project. If no branch is 
 * specified for a project, the default [deployBranch] will be used. The
 * same semantics as for the deploy locations are used.
 */
$DEPLOY_BRANCH = array(
	'cool-site' => 'development',
);


/* Omit PHP closing tag to help avoid accidental output */
