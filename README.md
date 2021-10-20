# FlyCubePHP

FlyCubePHP is an MVC Web Framework developed in PHP and repeating the ideology and principles of building WEB applications, 
embedded in Ruby on Rails. The main task set during the development was a quick and flexible transfer of project code 
from Ruby on Rails to PHP with a minimum number of changes to the code base of projects, and maintaining application performance.

An additional functionality added to the FlyCubePHP core is the plug-in mechanism support, 
which allows you to extend or change the functionality of your application, taking into account the plug-in dependencies during operation. 
In terms of its structure, the plug-in partially repeats the architecture of the application, making the necessary changes to it.

Currently FlyCubePHP supports
-----------------------------

- creating projects that architecturally repeat Ruby on Rails 5 projects:
  - creation of controllers (gui / api);
  - creation of data models;
  - creation of templates for WEB pages;
  - creation of helper classes for access from WEB page templates;
  - creation of JavaScript files with automatic assembly in * .min.js in production mode and processing of directives:
    ```bash
    = require ...
    = require_tree ...
    ```
  - creation of Sass Stylesheets files with automatic assembly in CSS and processing of directives:
    ```bash
    = require ...
    = require_tree ...
    ```
  - access to project resources (images, files, etc.) using the Asset Pipeline.
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

##Third-party dependencies

These dependencies are required for the framework to work correctly: 
- Twig-2.x - used to generate and render templates for web pages (https://github.com/twigphp/Twig/archive/refs/tags/v2.12.5.zip);
- JShrink - used to build * .min.js files in production mode (https://github.com/tedious/JShrink/archive/refs/tags/v1.1.0.zip);
- ScssPhp - used to build from Sass Stylesheets files to css (https://github.com/scssphp/scssphp/archive/refs/tags/v1.5.2.zip);
- Monolog - used to application logging (https://github.com/Seldaek/monolog/archive/refs/tags/1.26.1.zip).

NOTE: required third party dependencies will be automatically downloaded when creating a new FlyCubePHP project. 
This behavior can be disabled by specifying the input argument:
```bash
--download-requires=false
```

##Usage

```bash
FlyCubePHP/bin> php ./fly_cube_php --help

Usage: ./fly_cube_php [options]

Options include:

    --help                              Show this message [-h, -?]
    --new                               Create new FlyCubePHP project
    --name=[VALUE]                      Set new project name
    --path=[VALUE]                      Set new project root path (optional; default: user home)
    --download-requires=[true/false]    Download FlyCubePHP requires in new project (optional; 
    default: true)

    --version                           Print the version [-v]
```

##Create new FlyCubePHP project

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

##Development guides

- [RUS](https://github.com/AnthonySnow887/FlyCubePHP/blob/main/FlyCubePHP_Development_Guide_RUS.pdf)
- ENG: Coming soon...

##Basic system requirements

- PHP >= 7.0

##Additional required PHP modules

- php7-ctype
- php7-json
- php7-mbstring
- php7-openssl
- php7-pdo
- php7-pgsql
- php7-posix
- php7-sqlite
- php7-xmlreader
- php7-xmlwriter
- php7-zip

##Operating systems tested

- OpenSUSE 15.1 and later
- CentOS 8
- Astra Linux SE 1.6 (Linux kernel: 4.15.3-1)

#Releases

Releases of FlyCubePHP are available on [Github](https://github.com/AnthonySnow887/FlyCubePHP).

##License

FlyCubePHP is licensed under the MIT License. See the LICENSE file for details.
