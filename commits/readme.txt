This folder is where commit metadata is stored by the gateway.php script when
Bitbucket's POST service hook is triggered.

Ideally, this folder should be placed out of reach from public (outside www or
public_html directories).

The process executing gateway.php and deploy.php scripts (usually the webserver
daemon process) MUST have write access this this folder. This is MANDATORY!
