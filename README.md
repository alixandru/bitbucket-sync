# BitBucket Sync #


This is a lightweight utility script that synchronizes the local file system with updates from a BitBucket project.


## Description ##

This script keeps your website in sync with the updates made on a BitBucket project.

It is intended to be used on a web-server which is reachable from the internet and which can accept POST requests coming from BitBucket servers. It works by getting all the updates from a BitBucket project and applying them to a local copy of the project files.

For example, supposing you have a website which is deployed on a shared-hosting server, and the source code is stored in a private repository in BitBucket. This script allows you to automatically update the deployed website each time you push changes to the BitBucket project. This way, you don't have to manually copy any file from your working directory to the hosting server.

BitBucket Sync will synchronize only the files which have been modified, thus reducing the network traffic and deploy times.

## Installation ##

### Prerequisites ###

This script requires PHP 5.3+ with **cURL** and **Zip** extensions enabled. It can be used with most shared web-hosting providers offering PHP support.

### Installation instructions ###

* Get the source code for this script from [BitBucket][], either using Git, or by downloading directly.
* Copy the source files to your web-server in a location which is accessible from the internet (usually `public_html`, or `www` folders).
* Adjust configuration file `config.php` with information related to your environment and BitBucket projects that you want to keep in sync (see **Configuration** section).
* Make sure all folders involved in the sync process are **write-accessible** (see `config.php` for details).
* Perform an initial import of each project, through which the project files are copied to the web-server file-system (see **Operation** section below).
* Configure each BitBucket project that you want to keep synchronized to send commit information to your web server through the POST service hook. Information should be posted to the `gateway.php` script (**mandatory!**). [See more information][Hook] on how to create a service hook in BitBucket. POST URL should be, for example, `http://mysite.ext/bitbucket-sync/gateway.php`.
* Start pushing commits to your BitBucket projects and see if the changes are reflected on your web server. Depending on the configuration, you might need to manually trigger the synchronization by accessing the `deploy.php` script through your web server (i.e. `http://mysite.ext/bitbucket-sync/deploy.php`).


## Operation ##

This script has two complementary modes of operation detailed below.

### 1. Full synchronization ###

The script runs in this mode when it is accessed through the web-server and has the `setup` GET parameter specified in the URL (`deploy.php?setup=my-project-name`).  In this mode, the script will get the full repository from BitBucket and deploy it locally. This is achieved through getting a zip archive of the project, extracting it locally and copying its contents over to the project location specified in the configuration file.
This operation mode does not necessarily need a POST service hook to be defined in BitBucket for the project and is generally suited for initial set-up of projects that will be kept in sync with this script.


#### Steps on how to get a project set up using full synchronization ####

1. If your repository is called *my-project-name*, you need to define it in the `config.php` file and to specify, at least, a valid location, accessible for writing by the web server process. Optionally you can state the branch from which the deployment will be performed.

2. After this step, simply access the script `deploy.php` with the parameter `setup=my-project-name` (i.e. `http://mysite.ext/bitbucket-sync/deploy.php?setup=my-project-name`). It is advisable to have *verbose mode* enabled in the configuration file, to see exactly what is happening.

   By default, the script will attempt to get the project from a BitBucket repository created under your name (i.e. if your user is `johndoe`, it will try to get the repository `johndoe/my-project-name`). If the project belongs to a team or to another user, use the URL parameter `team` to specify it. For example, accessing `http://mysite.ext/bitbucket-sync/deploy.php?setup=my-project-name&team=doeteam` will fetch the project `doeteam/my-project-name`. Useful also for forks.

   Full synchronization mode also supports cleaning up the destination folder before attempting to import the zip archive. This can be done by specifying the `clean` URL parameter (i.e. `http://mysite.ext/bitbucket-sync/deploy.php?setup=my-project-name&clean=1`). When this parameter is present, the contents of the project location folder (specified in the configuration file) will be deleted before performing the actual import. Use this with caution.

   Once the import is complete, you can go on and setup the service hook in BitBucket and start pushing changes to your project.



### 2. Commit synchronization ###

This is the default mode which is used when the `deploy.php` script is accessed with no parameters in the URL. To work in this mode, the script needs to read the commit information received from BitBucket, so a POST service hook **must be configured** before changes can be automatically synchronized. In this mode, the script updates only the files which have been modified in a commit. If a file is changed by multiple commits it will be deployed only once, with the latest content, thus reducing network traffic. The entire sync process is described below.


#### The process flow of commit synchronization ####

1. You make one or more commits and push to BitBucket.

2. BitBucket receives the changes and checks for any service hooks configured for the project. Since there is a POST hook defined (see **Installation** section above), it makes a HTTP request (POST) to `gateway.php` script. This request contains information about the commits that were pushed (which files have been added, updated or deleted, what branches were affected, etc).

3. The `gateway.php` script records the request from BitBucket in a file stored locally in `commits` folder. This file will then be read when performing the actual sync. Storing data in a local file allows retrying the synchronization in case of failure.

4. The `deploy.php` script is invoked (either automatically from `gateway.php`, manually through a browser, or through a cron job on the web-server). This script will perform the actual synchronization by reading the local file containing the commit meta-data and requesting from BitBucket the content of files which have been changed. It will then update the local project files, one by one. After all files are updated, the meta-data file from `commits` folder is deleted to prevent synchronizing the same changes again.

5. If synchronization fails, the commit files (containing the commit meta-data) are not deleted, but preserved for later processing. They can be processed again by specifying the `retry` GET parameter when invoking `deploy.php` (i.e. `deploy.php?retry`).

Note: since files are updated one by one, there is a risk of having the website in an inconsistent state until all files are updated. It is recommended to trigger the actual synchronization (step 4) only when there is a low activity on the website.



  [BitBucket]: https://bitbucket.org/alixandru/bitbucket-sync
  [Hook]: https://confluence.atlassian.com/display/BITBUCKET/POST+hook+management


## Configuration ##

Firstly the script needs to have access to your BitBucket project files through the BitBucket API. If your project is private, you need to provide the user name and password of a BitBucket account with read access to the repository.

Then the script needs to know where to put the files locally once they are fetched from the BitBucket servers. The branch of the repository to deploy and a few other items can also be configured.

Optionally, the scripts can be configured to require authorization in order to operate. Different authorization codes can be defined for the `gateway.php` script (which accepts commit information from BitBucket) and for the `deploy.php` script (which triggers the synchronization). The key must be passed through the `key` URL parameter when accessing the scripts. i.e. `deploy.php?key=predefined-deploy-key` or `gateway.php?key=predefined-gw-key`. Note that the BitBucket POST service hook must be updated with the correct URL in this case.

All of this information can be provided in the `config.php` file. Detailed descriptions of all configuration items is contained as comments in the file.

**Important!** Make sure all folders specified in the `config.php` have write permission for the user under which the web-server process runs. This is mandatory in order for the script to be able to store commit meta-data files locally.


## Change log ##

**v2.2.1**

* Various fixes and improvements. Contributed by [n4r-c0m](https://bitbucket.org/n4r-c0m).


**v2.2.0**

* Added the ability to require authorization when posting commit meta-data (through gateway.php) or when deploying (through deploy.php). Authorization can be done by specifying a previously established key when accessing the scripts. Contributed by [Juha Ryhanen](https://bitbucket.org/ryhanen).


**v2.1.0**

* Added the ability to retry failed synchronizations.
* Updated the documentation to make it more clear


**v2.0.1**

* Improved the full synchronization support.
* Cleaning the destination before import is now an optional operation, triggered only by `clean` parameter
* Fixed issue with branch which was not correctly determined if multiple branches were pushed


**v2.0.0**

* Implemented the ability to fully synchronize the project by getting the entire project content at once.


**v1.0.0**

* Initial public release, which supports only synchronizing files which were changed.



## Disclaimer ##
This code has not been extensively tested on highly active, large BitBucket projects. You should perform your own tests before using this on a live (production) environment for projects with a high number of updates.

This code has been tested with Git repositories only, however Mercurial projects should theoretically work fine as well.


## License ##
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.