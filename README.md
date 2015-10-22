ps-scripts
==========

ps-scripts is a set of scripts that make deploying services on Linux a best practice.

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
   - Simple backup system included: service file, MySQL databases
   - Popular packages like apache, gunicorn, nagios, openssl or wordpress already included. Easy to add new build scripts for the
     missing ones
   - Integrated logging subsystem in log directory. There you will find apache logs, but also service backup logs, service deployment logs...
     Unified and simple format makes reading (or filtering) service logs a pleasure. The logs are kept outside Docker image, so you do not
     need to worry about missing them.

Coming soon...

   - service mirroring: automatically keep a clone of a running service. If the "master" dies, you can switch to the clone while fixing
     the environment where master ran
   - instacreation script: collect all the preliminars in a single script

Getting started
===============

Let's deploy a WordPress-based service. We run all commands in the "server environment". In this case, the server hostname is "dockerserver"
(the service will run inside a Docker container)

Service root
------------

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

   # create the basic layout
   mkdir -p etc/backup
   mkdir -p usr/bin usr/lib usr/src
   mkdir -p data/
   mkdir etc/apache2
   mkdir -p log/apache2

Configuration
-------------

The key point is defining what you want, using the configuration files.

    /services/wp-example/scripts/config/mkservice_cfg -n wp-example -i apache,cron,wordpress > /services/wp-example/etc/service.cfg

mkservice_cfg uses generic port numbers. You may want to review the generated service.cfg

Now apache config:

    /services/wp-example/scripts/config/apache2/mkhttpd_conf > /services/wp-example/etc/apache2/httpd.conf
    /services/wp-example/scripts/config/apache2/mkservice_conf > /services/wp-example/etc/apache2/service.conf
    cp /services/wp-example/scripts/config/apache2/envvars /services/wp-example/etc/apache2

Docker configuration:

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

And you are done!

    docker ps
    CONTAINER ID        IMAGE               COMMAND                CREATED             STATUS              PORTS                                                                  NAMES
    9425ae35ff5b        debian:stable-ssh   "/services/wp-exampl   3 seconds ago       Up 2 seconds        0.0.0.0:9632->22/tcp, 0.0.0.0:9680->9680/tcp                           wp-example 

All the required software packages are built from source the first time the service runs. You can follow the progress in log/service.log and log/build.log:

~~~~
tail -f log/*
==> log/apt.log <==
1445439390 2015-10-21 14:56:30 a12b328dc467               -build      libstdc++6-4.7-doc make-doc man-browser ed diffutils-doc perl-doc
1445439390 2015-10-21 14:56:30 a12b328dc467               -build      libterm-readline-gnu-perl libterm-readline-perl-perl libpod-plainer-perl
1445439390 2015-10-21 14:56:30 a12b328dc467               -build      The following NEW packages will be installed:

==> log/build.log <==
1445439388 2015-10-21 14:56:28 a12b328dc467               -build      httpd install started
1445439388 2015-10-21 14:56:28 a12b328dc467               -build      installing build environment

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
~~~~~


Since all the scripts are bash, you can dig into the details of any step invoking them with bash -x

Usage examples
==============

To start our example service as a container:

   /services/wp-example/scripts/container start

You can ssh into your container. Following the example,

    ssh -p 9632 root@dockerserver

To stop the service:

   /services/wp-example/scripts/container stop

Under the hood
==============

To document: architecture details

Licensing
=========

ps-scripts is licensed under GPL license, version 2.3. See LICENSE for full text.


Related projects
================


Issues and troubleshooting
==========================

Currently, ps-scripts is tested only in Debian. Some minor issues might happen in other Linux flavours

Feel free to contact me regarding suggestions, requests or any issue you find.

Author
======

Copyright 2014,2015 by Jesus Cuenca-Alba (jesus.cuenca@gmail.com), Biocomputing Unit - CNB/CSIC.
