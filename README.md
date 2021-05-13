# Kiroku

### A Journalling system that tries to be less overbearing than Evernote and DayOne

The Following is a list of notes regarding the use and configuration of Kiroku.

## General Requirements

You will need:

* a web server running Apache 2.4.x and PHP 7.0 or newer
* MySQL 8.0 or above (MySQL 5.x and MariaDB 10.x are feasible, but not supported)

***Note:** Watch out for MySQL 8.0.22, which has a bug that causes INSERTs to tables with triggers to crash the database engine on occasion.*

## LAMP Configuration Notes

### Linux Notes

This code has been tested to run on Ubuntu Server 20.04 LTS. That said, it should run on any version of Linux released in the last 5 years. Your mileage may very. Test often. Test well.

### Apache Notes

The following modules must be loaded:

* mod-php
* mod-rewrite
* mod-headers

### MySQL Notes

MySQL 8.0 is the database engine used for all testing, development, and deployment. The tables are all configured with InnoDB. Other database engines such as RocksDB, XtraDB, and MyISAM have not been tested, so reliability is unknown. Avoid using MyISAM as this engine has been deprecated and is not ideal for highly concurrent environments.

**Important:**

* Kiroku makes use of stored procedures and triggers for the vast majority of its functions. MySQL 8.0.22 has issues when triggers are called *after* a stored procedure executes. If you are running MySQL 8.0.22, you will need to either downgrade to 8.0.19 or upgrade to something much newer.
* Starting a *new* database on MariaDB should work properly. Migrating an existing Kiroku database from MySQL to MariaDB will likely result in errors.

### PHP Notes

The following modules are required:

* mbstring
* dev
* xml
* json
* mysql
* gd
* curl
* pear

### Other Setup Requirements

In addition to the basic LAMP stack, the following items need to be taken into account.

* the `htaccess` file in `/public` must be renamed `.htaccess`
* Apache must be configured to honour the `.htaccess` overrides
* Kiroku can use Amazon S3 storage for files, but is off by default
* Kiroku can enforce HTTPS redirects (and ideally should use it)
* Kiroku is designed to run on servers with as little as 512MB RAM

### Basic Web Server -- Minimum Recommended

* Ubuntu Server 20.04 LTS
* Dual-Core CPU (x86/x64/ARM)
* 2GB RAM
* 10GB Storage

### Windows Configuration Notes

It is not recommended that Kiroku run on Windows in a WAMP-like fashion. It has not been tested and, as of this writing, will not be supported.

### MAMP Configuration Notes

Do not do this. Please.

### XAMPP Configuration Notes

If you're running Linux, *RUN LINUX*. There is no need for XAMPP when Apache, MySQL, and PHP are already well-supported.

### Optional Components

There are some optional pieces to the puzzle that might make things a little better. These things include:

* something to drink
* good music
* a faithful dog
