# BitBucket Sync #


This is a lightweight utility script that synchronizes the local file system with updates from a BitBucket project.


## Description ##

This script keeps the files deployed on dedicated or shared-hosting web-servers in sync with the updates made on a BitBucket project.

It is intended to be used on a web-server which is reachable from the internet and which can accept POST requests coming from BitBucket. It works by getting all the updates from a BitBucket project and applying them to a local copy of the project files. 

For example, supposing you have a website which is deployed on a shared-hosting server, and the source code is stored in a private repository in BitBucket. This script allows you to automatically update the deployed website each time you push changes to the BitBucket project. This way, you don't have to manually copy any file from your working directory to the hosting server.

BitBucket Sync will synchronize only the files which have been modified, thus reducing the network traffic and deploy times.

## Installation ##

### Prerequisites ###

This script requires PHP 5.3+ with cURL extension enabled and any web-server offering PHP support (most shared web hosting solutions should work fine).

### Installation instructions ###

* Get the source code for this script from [BitBucket][], either using [Git][], or downloading directly:

    - To download using git, install git and then type

        `git clone git@bitbucket.org:alixandru/bitbucket-sync.git bitbucket-sync`
		
    - To download directly, go to the [project page][BitBucket] and click on **Download**

* Copy the source files to your web-server in a location which is accessible from the internet (usually `public_html`, or `www` folders) 

* Adjust configuration file `config.php` with information related to your environment and BitBucket projects that you want to keep in sync (see **Configuration** section).

* Configure all your BitBucket projects that you want to keep synchronized to post commit information to your web server through the POST service hook. [See more information][Hook] on how to create a service hook in BitBucket. The POST URL should point to the `gateway.php` script. For example, `http://mysite.ext/bitbucket-sync/gateway.php`.

* Start pushing commits to your BitBucket projects and see if the changes are reflected on your web server. Depending on the configuration, you might need to manually trigger the synchronization by accessing the `deploy.php` script through your web server (i.e. `http://mysite.ext/bitbucket-sync/deploy.php`).

### Notes ###

This script does not support the initial import of project data into the server's local file system. If you set up the POST hook after committing files to your BitBucket project, you need to manually copy an initial dump of your project files to the local server where BitBucket Sync is installed. After this, BitBucket Sync will keep these files synchronized as new commits and pushes are performed.

  [Git]: http://git-scm.com/
  [BitBucket]: https://bitbucket.org/alixandru/bitbucket-sync
  [Hook]: https://confluence.atlassian.com/display/BITBUCKET/POST+hook+management


## Configuration ##

Firstly the script needs to have access to your BitBucket project files through the BitBucket API. If your project is private, you need to provide the user name and password of a BitBucket account with read access to the repository.

Then the script needs to know where to put the files locally once they are fetched from the BitBucket servers. The branch of the repository to deploy and a few other items can also be configured. 

All of this information can be provided in the `config.php` file. Detailed descriptions of all configuration items is contained as comments in the file.


## Change log ##

**v1.0.0**

* Initial public release.



## Disclaimer ##
This code has not been extensively tested on highly active, large BitBucket projects. You should perform your own tests before using this on a live (production) environment for projects with a high number of updates.

This code has been tested with Git repositories only, however Mercurial projects should theoretically work fine as well.


## License ##
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

