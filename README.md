# FlyCubePHP

[![License](https://img.shields.io/github/license/AnthonySnow887/FlyCubePHP)](https://github.com/AnthonySnow887/FlyCubePHP/blob/master/LICENSE)
[![Latest Release](https://img.shields.io/github/v/release/AnthonySnow887/FlyCubePHP?label=release)](https://github.com/AnthonySnow887/FlyCubePHP/releases)
![Last Commit](https://img.shields.io/github/last-commit/AnthonySnow887/FlyCubePHP/develop)

FlyCubePHP is an MVC Web Framework developed in PHP and repeating the ideology and principles of building WEB applications, 
embedded in Ruby on Rails. The main task set during the development was a quick and flexible transfer of project code 
from Ruby on Rails to PHP with a minimum number of changes to the code base of projects, application behavior logic,
and maintaining application performance.

An additional functionality added to the FlyCubePHP core is the plug-in mechanism support, 
which allows you to extend or change the functionality of your application, taking into account the plug-in dependencies during operation. 
In terms of its structure, the plug-in partially repeats the architecture of the application, making the necessary changes to it.

Currently FlyCubePHP supports
-----------------------------

- creating projects that architecturally repeat Ruby on Rails 5 / 6 projects:
  - creation of controllers (gui / api);
  - creation of data models;
  - creation of templates for WEB pages;
  - creation of helper classes for access from WEB page templates;
  - creation of JavaScript files with automatic assembly in * .min.js in production mode and processing of directives:
    ```bash
    = require ...
    = require_tree ...
    ```
  - creation of Sass Stylesheets files with automatic assembly in CSS (or *.min.css in production mode) and processing of directives:
    ```bash
    = require ...
    = require_tree ...
    ```
  - creating api dock files for controllers based on the JSON format;
  - creating help-doc files based on the Markdown format;
  - access to project resources (images, files, etc.) using the Asset Pipeline;
  - to speed up the loading of resources, implemented:
    - automatic compression of assets using gzip or deflate;
    - automatic check of ETag header;
  - creation of Action Cable classes for working with Web Sockets channels.
- Web Sockets support using FlyCubePHP WS Service (automatically created when generating new project files 
  or when checking an existing project for missing directories and files);
- creation of plugins that extend or change the functionality of your application;
- creation of database migrations and a wide range of tools for working with migrations;
- creation of extensions to the FlyCubePHP kernel allowing:
  - add support for the required databases;
  - add support for the required databases for the migration system;
  - add additional helper classes for access from WEB page templates;
  - add support for the necessary tools for preprocessing and assembling JavaScript files;
  - add support for the necessary tools for preprocessing and assembling Stylesheets files.
  
Supported databases
-------------------

- SQLite 3;
- PostgreSQL 9.6 or later.
- MySQL/MariaDB (tested on MariaDB 10.2.36)

Third-party dependencies
------------------------

These dependencies are required for the framework to work correctly: 
- Twig-2.x - used to generate and render templates for web pages (https://github.com/twigphp/Twig/archive/refs/tags/v2.12.5.zip);
- JShrink - used to build * .min.js files in production mode (https://github.com/tedious/JShrink/archive/refs/tags/v1.4.0.zip);
- ScssPhp - used to build from Sass Stylesheets files to css (https://github.com/scssphp/scssphp/archive/refs/tags/v1.8.1.zip);
- Monolog - used to application logging (https://github.com/Seldaek/monolog/archive/refs/tags/1.26.1.zip).

NOTE: required third party dependencies will be automatically downloaded when creating a new FlyCubePHP project. 
This behavior can be disabled by specifying the input argument:
```bash
--download-requires=false
```

Usage
-----

```bash
FlyCubePHP/bin> php ./fly_cube_php --help

Usage: ./fly_cube_php [options]

Options include:

    --help                              Show this message [-h, -?]
    --new                               Create new FlyCubePHP project
    --name=[VALUE]                      Set new project name
    --path=[VALUE]                      Set new project root path (optional; default: user home)
    --download-requires=[true/false]    Download FlyCubePHP requires in new project (optional; default: true)
    --version                           Print the version [-v]
    --latest-version                    Select latest version from GitHub [-lv]
    --upgrade                           Update FlyCubePHP core version of your project to the latest version 
    --force                             Forced update flag 

Examples:

 1. Create new FlyCubePHP project:
     ./fly_cube_php --new --project --name=MyProject

 2. Create new FlyCubePHP project without requires:
     ./fly_cube_php --new --project --name=MyProject --download-requires=false

 3. Create new FlyCubePHP project specifying the installation directory:
     ./fly_cube_php --new --project --name=MyProject --path=/home/test/projects
     
 4. Upgrade an existing FlyCubePHP project:
     ./fly_cube_php --upgrade --path=/home/test/projects/MyProject
     
 5. Force upgrade an existing FlyCubePHP project:
     ./fly_cube_php --upgrade --force --path=/home/test/projects/MyProject
```

Create new FlyCubePHP project
-----------------------------

```bash
FlyCubePHP/bin> php ./fly_cube_php --new --name=MyProject 

=== FlyCubePHP: Create new project "MyProject" === 
 - Create project dir: OK 
 - Create project tree: OK 
 - Copy FlyCubePHP core: OK 
 - Copy FlyCubePHP templates: OK 
 - Download requires [Twig]: OK 
 - Download requires [JShrink]: OK 
 - Download requires [ScssPhp]: OK 
 - Download requires [Psr/Log]: OK 
 - Download requires [Monolog]: OK 
 - Unzip requires [Twig]: OK 
 - Unzip requires [JShrink]: OK 
 - Unzip requires [ScssPhp]: OK 
 - Unzip requires [Psr/Log]: OK 
 - Unzip requires [Monolog]: OK 
=== FlyCubePHP: Create project success. === 
=== FlyCubePHP: Dir: /home/[USER]/FlyCubePHProjects/MyProject ===
```

Supported commands in the project
---------------------------------

```bash
MyProject/bin> ./fly_cube_php -h 

Usage: ./fly_cube_php [options]

Options include:

    --help                              Show this message [-h, -?]
    --version                           Print the version [-v]
    --latest-version                    Select latest version from GitHub [-lv]
    --output=[true/false]               Show output (optional)
    --env=[VALUE]                       Set current environment (production/development; default: development)
    --upgrade                           Update FlyCubePHP core version of your project to the latest version 
    --force                             Forced update flag
    
    --new [option]                      Create new object (project/controller/model/migration/plugin/...etc)
    --project                           Create new FlyCubePHP project
    --controller                        Create new controller
    --controller-api                    Create new API controller
    --model                             Create new model
    --migration                         Create new migration
    --channel                           Create new action cable channel
    --plugin                            Create new FlyCubePHP plugin
    --plugin-gui                        Create new FlyCubePHP Gui plugin
    --plugin-controller                 Create new FlyCubePHP plugin controller
    --plugin-controller-api             Create new FlyCubePHP plugin API controller
    --plugin-model                      Create new FlyCubePHP plugin model
    --plugin-migration                  Create new FlyCubePHP plugin migration
    --path=[VALUE]                      Set new project root path (optional; default: user home)
    --download-requires=[true/false]    Download FlyCubePHP requires in new project (optional; default: true)
    --name=[VALUE]                      Set new object name
    --plugin-name=[VALUE]               Set plugin name
    --actions=[VALUE(,VALUE...)]        Set new controller actions list
    
    --db-create                         Create database for current environment
    --db-create-all                     Create databases for all environments (development and production)
    --db-drop                           Drop database for current environment
    --db-drop-all                       Drop databases for all environments (development and production)
    --db-setup                          Init database for current environment
    --db-reset                          Re-Init database for current environment
    --db-seed                           Load database Seed.php file
    --db-migrate                        Start database migrations
    --db-migrate-redo                   Start re-install last database migration
    --db-migrate-status                 Select migrations status
    --db-rollback                       Start uninstall last database migration
    --db-rollback-all                   Start uninstall all database migrations
    --db-version                        Select current database migration version
    --db-schema-dump                    Create database schema dump
    --db-schema-load                    Re-Create database and load schema dump     
    --to-version=[VALUE]                Set needed migration version (optional; if 0 - uninstall all migrations)
    --step=[VALUE]                      Set needed number of steps for uninstall (re-install) migrations (optional; default: 1)
    
    --routes                            Show application routes
    
    --secret                            Create application secret key
    --secret-length=[VALUE]             Set application secret key length (value > 0)
    
    --check-project                     Check the project catalogs, base files and create the missing or change their rights to the necessary
    --assets-precompile                 Build assets cache
    --skip-rendering=[VALUE]            Skip rendering graphics controllers and their page templates (optional; default: false) 
    --assets-list                       Show list of loaded assets
    --javascripts=[VALUE]               JavaScript assets display filter (optional; default: true) 
    --stylesheets=[VALUE]               Stylesheet assets display filter (optional; default: true)
    --images=[VALUE]                    Image assets display filter (optional; default: true)
    --clear-cache                       Clear all application cache
    --clear-logs                        Clear all application logs
    --clear-php-sessions                Clear all php sessions
    
Examples:

 1. Create new FlyCubePHP project:
     ./fly_cube_php --new --project --name=MyProject
     
 2. Create new FlyCubePHP project without requires:
     ./fly_cube_php --new --project --name=MyProject --download-requires=false

 3. Create new controller without actions:
     ./fly_cube_php --new --controller --name=Example

 4. Create new controller with actions:
     ./fly_cube_php --new --controller --name=Example --actions=act_1,act_2
    
 5. Create new action cable channel:
     ./fly_cube_php --new --channel --name=Example
     
 6. Create new action cable channel with actions:
     ./fly_cube_php --new --channel --name=Example --actions=act_1,act_2
    
 7. Create new plugin:
     ./fly_cube_php --new --plugin --name=ExamplePlugin
    
 8. Create new gui plugin:
     ./fly_cube_php --new --plugin-gui --name=ExamplePlugin
    
 9. Create new plugin controller:
     ./fly_cube_php --new --plugin-controller --plugin-name=ExamplePlugin --name=ExampleController
    
10. Install all migrations:
     ./fly_cube_php --db-migrate

11. Install needed migrations:
     ./fly_cube_php --db-migrate --to-version=20210309092620
    
12. Uninstall last migration:
     ./fly_cube_php --db-rollback
    
13. Uninstall last N-steps migrations:
     ./fly_cube_php --db-rollback --step=3

14. Upgrade current FlyCubePHP project:
     ./fly_cube_php --upgrade
          
15. Upgrade an existing FlyCubePHP project:
     ./fly_cube_php --upgrade --path=/home/test/projects/MyProject
     
16. Force upgrade an existing FlyCubePHP project:
     ./fly_cube_php --upgrade --force --path=/home/test/projects/MyProject
```

Project update with FlyCubePHP core version lower than 1.3.0
------------------------------------------------------------

```bash
FlyCubePHP/bin> php ./fly_cube_php --upgrade --path=/home/[USER]/FlyCubePHProjects/MyProject

=== FlyCubePHP: Upgrade project ===

  Project name: MyProject
  Project path: /home/[USER]/FlyCubePHProjects/MyProject
  Project ver.: 1.1.0
   Latest ver.: 1.3.0

The project will be updated to version 1.3.0. Continue? [yes/no] (yes): y

  - Checking project catalogs: OK
  - Download latest version [FlyCubePHP]: OK
  - Unzip latest version [FlyCubePHP]: OK
  - Download requires [Twig]: OK
  - Download requires [JShrink]: OK
  - Download requires [ScssPhp]: OK
  - Download requires [Psr/Log]: OK
  - Download requires [Monolog]: OK
  - Unzip requires [Twig]: OK
  - Unzip requires [JShrink]: OK
  - Unzip requires [ScssPhp]: OK
  - Unzip requires [Psr/Log]: OK
  - Unzip requires [Monolog]: OK

  Look at the upgrade files:
    - upgrade to v1.2.0: /vendor/FlyCubePHP/UPGRADE-1.2.0.md

  Upgrade project to latest version: SUCCESS

=== FlyCubePHP ====================
```

Project upgrade with FlyCubePHP core version 1.3.0 or older
-----------------------------------------------------------

```bash
MyProject/bin> ./fly_cube_php --upgrade

=== FlyCubePHP: Upgrade project ===

  Project name: MyProject
  Project path: /home/[USER]/FlyCubePHProjects/MyProject
  Project ver.: 1.3.0
   Latest ver.: 1.5.0

The project will be updated to version 1.5.0. Continue? [yes/no] (yes): y

  - Checking project catalogs: OK
  - Download latest version [FlyCubePHP]: OK
  - Unzip latest version [FlyCubePHP]: OK
  - Download requires [Twig]: OK
  - Download requires [JShrink]: OK
  - Download requires [ScssPhp]: OK
  - Download requires [Psr/Log]: OK
  - Download requires [Monolog]: OK
  - Unzip requires [Twig]: OK
  - Unzip requires [JShrink]: OK
  - Unzip requires [ScssPhp]: OK
  - Unzip requires [Psr/Log]: OK
  - Unzip requires [Monolog]: OK

  Look at the upgrade files:
    - upgrade to v1.4.0: /vendor/FlyCubePHP/UPGRADE-1.4.0.md
    - upgrade to v1.5.0: /vendor/FlyCubePHP/UPGRADE-1.5.0.md

  Upgrade project to latest version: SUCCESS

=== FlyCubePHP ====================
```

Development guides
------------------

- [RUS](https://github.com/AnthonySnow887/FlyCubePHP/blob/main/FlyCubePHP_Development_Guide_RUS.pdf)
- ENG: Coming soon...

Basic system requirements
-------------------------

- PHP >= 7.0

Additional required PHP modules
-------------------------------

- php7-ctype
- php7-curl
- php7-json
- php7-mbstring
- php7-mysql
- php7-openssl
- php7-pcntl
- php7-pdo
- php7-pgsql
- php7-posix
- php7-redis
- php7-sockets
- php7-sqlite
- php7-xmlreader
- php7-xmlwriter
- php7-zip
- php7-zlib

Operating systems tested
------------------------

- OpenSUSE 15.1 and later
- CentOS 8
- Astra Linux SE 1.6 (Linux kernel: 4.15.3-1)

Releases
--------

Releases of FlyCubePHP are available on [Github](https://github.com/AnthonySnow887/FlyCubePHP/releases).

License
-------

FlyCubePHP is licensed under the MIT License. See the LICENSE file for details.
