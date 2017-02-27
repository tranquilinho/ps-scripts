ps-scripts
==========

"A set of scripts that make deploying services on Linux a best practice."

A service can be a simple WordPress blog, or a buildbot master, or a Django webapp...

ps stands for Portable Services, since one of the core ideas is that it should be easy to migrate a service to a
different "server", be it a Docker container, a real server, or a virtual machine.

Features
========

   - Run your service in a Docker container, in a virtual machine or in real hardware. No need to worry about
     the environment.
   - Everything shares a single root, so it is trivial to move the service with all its dependencies.
   - All the configuration is under the service's etc - no need to wonder where a config file might be
   - The key parameters of the service are collected in service.cfg. This allows for easy mutation of
     the service
   - Create your custom configuration easily with the configuration scripts
   - Simple versioning and updates: automatically build the versions of your dependencies that you need, then use
     the one that suits you best. Or try different versions in different instances of the service.
   - Start the service as a whole with a single script. Or restart one of the dependencies.
   - Simple backup system included: service files, MySQL databases
   - Popular packages like apache, gunicorn, nagios, openssl or wordpress already included. Easy to add new build scripts for the
     missing ones
   - Integrated logging subsystem in log directory. There you will find apache logs, but also service backup logs, service deployment logs...
     Unified and simple format makes reading (or filtering) service logs a pleasure. The logs are kept outside Docker image, so you do not
     need to worry about missing them. The logs can be rotated with the integrated logrotate.

Coming soon...

   - service mirroring: automatically keep a clone of a running service. If the "master" dies, you can switch to the clone while fixing
     the environment where master ran
   - instacreation script: collect all the preliminars in a single script
   - tests: in the spirit of [Babushka](http://babushka.me/)

Getting started
===============

You can either reuse a recipe from the "recipes" subdirectory, or build your custom service from smaller pieces.

Recipes
-------

"High-level" scripts that deploy complex services making the most of the portable service architecture. 

   - [guacamole](https://github.com/tranquilinho/ps-scripts/blob/testing/recipes/guacamole) - remote desktop service from a standard HTML5 browser. Based on [guacamole](http://guacamole.incubator.apache.org/)

   1. Download the recipe
   ```wget https://raw.githubusercontent.com/tranquilinho/ps-scripts/testing/recipes/guacamole
   ```

   2. Run the recipe. In this example, the web user is john
   ```bash guacamole -g john
   ```

   3. Check the final configuration files, as indicated by the recipe

Operation summary
-----------------

In general, the main "entry point" to any service is the `service-manager` script, which handles service start & stop.

Since (in this architecture) persistence is only ensured for data inside the service root (for example, `/services/mediawiki`), the `service-prereq` script is called from `service-manager` to perform the one-time ("boot") initial setup.

Depending upon the specifics of the service and the virtualization environment, other scripts might be used (@see [Service management](#service-management) below):

   - in the case of docker containers, the container is first instantiated with the `container` script. Once the container is up and running, the
`service-manager` script is called as usual. By default, when the container is stopped (`container stop`) everything outside service root is lost, and the next time the container is created, it will start "from scratch" (more precisely, from the Docker image specified in `etc/docker.cfg`)

   - in the case of "bare-metal" servers and virtual machines (Virtualbox), the `service-manager` script is called directly (typically, from the init.d subsystem). Virtualbox may (or may not) have persistence at image level. "Bare-metal" servers usually are persistant by default.

Configuration is centralized in `etc/service.cfg`. When other configuration files are derived from `service.cfg`, its "transform script" should be called again to update the derived config. For example: after changing apache2-related settings in `service.cfg`, rerun the `config/apache2/mkservice_conf`. All the "transform" scripts are available under `config` subdirectory.

In short:

DOCKER: `container start` > `etc/container-init` > `etc/service-manager` > `etc/service-prereq` > `service-specific daemons` > `sshd`

BARE-METAL / VM: `etc/service-manager` > `etc/service-prereq` > `service-specific daemons` 


Build your service step by step
===============================

Let's deploy a WordPress-based service. We run all commands in the "server environment". In this case, the server hostname is "dockerserver"
(the service will run inside a Docker container)

First, decide where you will deploy the service:

       cd /services/wp-example

Installation
------------

Actual installation of the scripts is pretty simple: clone them from this repo as "scripts":

    git clone https://github.com/tranquilinho/ps-scripts.git scripts
    
To keep track of all the configuration, one can use a git repository:

    git init
    git remote add origin ssh://git@gitserver/services/wp-example.git

In this example I will use git sparringly (since git is not the focus of the example). Feel free to apply your own revision policy.

```
   # create the basic layout
   mkdir -p etc/backup
   mkdir -p usr/bin usr/lib usr/src
   mkdir -p data/
   mkdir etc/apache2
   mkdir -p log/apache2
```
   
Configuration
-------------

The key point is defining what you want, using the configuration files.

    /services/wp-example/scripts/config/mkservice_cfg -n wp-example -i apache,cron,wordpress > /services/wp-example/etc/service.cfg

*mkservice_cfg* uses generic port numbers. You may want to review the generated service.cfg

Now apache config:

    /services/wp-example/scripts/config/apache2/mkhttpd_conf > /services/wp-example/etc/apache2/httpd.conf
    /services/wp-example/scripts/config/apache2/mkservice_conf > /services/wp-example/etc/apache2/service.conf
    cp /services/wp-example/scripts/config/apache2/envvars /services/wp-example/etc/apache2
    # If you want to use HTTPs...
    # ... 1) if you do not have a SSL keypair, create one (obviously, being "self-made" it will require explicit approval in the client browser):
    /services/guacamole/scripts/config/make-snakeoil-cert -n wpe -C ES -c Madrid -O Organization -o Unit -F my-sample-domain.com -e info@my-sample-domain.com
    # ... 2) add it to apache config:
    Listen 443
    <VirtualHost *:443>
       # SSL configuration
       SSLEngine on
       # Self-generated
       SSLCertificateFile      /services/wp-example/etc/apache2/ssl/wpe.pem
       SSLCertificateKeyFile   /services/wp-example/etc/apache2/ssl/wpe-private.key
		   

Docker configuration
--------------------

       /services/wp-example/scripts/config/mkdocker_cfg -n wp-example > /services/wp-example/etc/docker.cfg
       /services/wp-example/scripts/config/mkcontainer_init -n wp-example > /services/wp-example/etc/container-init

Currently, you need to review docker.cfg to set which image to use. You may also want to take a look at etc/container-init.

service-manager will start and stop apache and cron. There are some additional prerrequisites that can be made explicit in etc/service-prereq:

		/services/wp-example/scripts/config/mkservice-manager > /services/wp-example/etc/service-manager
		cat > /services/wp-example/etc/service-prereq <<"EOF"
		#!/bin/bash
		readonly service_root=/services/wp-example

		. /services/wp-example/etc/service.cfg

		# run apache as www user, with UID 4000
		${scripts_base}/create-user 4000 www ${web_home}
		chown -R www.www ${web_home}

		# setup git for mysql backup
		${scripts_base}/build/git
		git config --global user.email "backup@wp-example"
		EOF

		chmod u+x /services/wp-example/etc/service-prereq /services/wp-example/etc/service-manager /services/wp-example/etc/container-init

		/services/wp-example/scripts/config/mkbackup_cfg -n wp-example -b mybackupserver:vg0:wp-example-backup-service:/backup/services/wp-example  > /services/wp-example/etc/backup/main.cfg

Copy your ssh public key as the service manager key:

     cp ~/.ssh/id_rsa.pub /services/wp-example/etc/admin_key

Keep track of all configuration:

    git add etc

And you are done!

    /services/wp-example/scripts/container start
    
    docker ps
    CONTAINER ID        IMAGE               COMMAND                CREATED             STATUS              PORTS                                                                  NAMES
    9425ae35ff5b        debian:stable-ssh   "/services/wp-exampl   3 seconds ago       Up 2 seconds        0.0.0.0:9632->22/tcp, 0.0.0.0:9680->9680/tcp                           wp-example 

All the required software packages are built from source the first time the service runs. You can follow the progress in log/service.log and log/build.log:

```
tail -f log/*
==> log/apt.log <==
1445440531 2015-10-21 15:15:31 fa185b5333ad     wp-example-build      debconf: falling back to frontend: Readline
1445440532 2015-10-21 15:15:32 fa185b5333ad     wp-example-build      Setting up file (1:5.22+15-2) ...
1445440532 2015-10-21 15:15:32 fa185b5333ad     wp-example-build      Setting up mailutils (1:2.99.98-2) ...


==> log/build.log <==
1445511319 2015-10-22 10:55:19 fa185b5333ad     wp-example-build      httpd install started
1445511319 2015-10-22 10:55:19 fa185b5333ad     wp-example-build      preconditions start apr
1445511320 2015-10-22 10:55:20 fa185b5333ad     wp-example-build  OK  preconditions finish apr
1445511361 2015-10-22 10:56:01 fa185b5333ad     wp-example-build      standard install start apr

==> log/service.log <==
1445439388 2015-10-21 16:56:28 heisenberg.cnb.csic.es                           Docker container start
1445439388 2015-10-21 16:56:28 heisenberg.cnb.csic.es                       OK  Container wp-example (a12b328dc4676c9982d5556ac1dbeee78dd44e664f266796acb0172ccb5969cc) started
1445439388 2015-10-21 14:56:28 a12b328dc467                       OK  Docker service start fix applied
1445439388 2015-10-21 14:56:28 a12b328dc467               -admin      installing admin pub key
1445439388 2015-10-21 14:56:28 a12b328dc467               -admin  OK  Admin key installed
1445439388 2015-10-21 14:56:28 a12b328dc467               -admin CRIT Expected known host /services/wp-example/etc/known_hosts not found 
1445439388 2015-10-21 14:56:28 a12b328dc467                           Starting 
1445439388 2015-10-21 14:56:28 a12b328dc467               -httpd      Starting web server apache2
1445439388 2015-10-21 14:56:28 a12b328dc467               -httpd      Installing apache2
1445439388 2015-10-21 14:56:28 a12b328dc467                      CRIT Build environment not ready
```

Log rotation is easy to enable:

1. Configure logrotate:

   /services/wp-example/scripts/config/mklogrotate_cfg -n wp-example > /services/wp-example/etc/logrotate.conf

2. Add logrotate to service's cron:

   echo "44 04  * * * root /services/wp-example/scripts/logrotate" > /services/wp-example/etc/cron/wp-example-logrotate


Service management
==================

To start our example service as a container:

    /services/wp-example/scripts/container start

You can ssh into your container. Following the example,

    ssh -p 9632 root@dockerserver

To stop the service:

    /services/wp-example/scripts/container stop

To start a service automatically, it must be called from boot scripts. Create an "init.d-compatible" boot script with `config/mkdebian_init`. In bare-metal and Virtualbox, you only need to place the boot script in `/etc/init.d` and enable it with `update-rc.d`.

`config/mkdebian_init -D` creates a boot script that is able to start the container (calling `container start`). Again, copy the script to `/etc/init.d` (in the container server) and enable it.

Under the hood
==============

!!!! Document: architecture details

Licensing
=========

ps-scripts is licensed under GPL license, version 3. See LICENSE for full text.


Related projects
================


Issues and troubleshooting
==========================

Currently, ps-scripts is tested only in Debian. Some minor issues might happen in other Linux flavours.

Since all the scripts are bash, you can dig into the details of any step invoking them with bash -x

Feel free to contact me regarding suggestions, requests or any issue you find.

Author
======

Copyright 2014-2016 by Jesus Cuenca-Alba (jesus.cuenca@gmail.com). Biocomputing Unit - CNB/CSIC.
