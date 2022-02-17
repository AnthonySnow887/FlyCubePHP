UPGRADE FROM 1.5.0/1.5.1 to 1.6.0
=================================

---
NOTE

After updating the project, you need to check for missing project files.

Example command:
```bash
MyProject/bin> ./fly_cube_php --check-project
```

---

Application config
------------------

 * The following sections have been added to the project configuration file ('config/application_env.conf').
   The absence of these sections in the configuration file will tell the FlyCubePHP core to use the default values.

   ### Help Doc Settings

   ```
   #
   # --- help doc settings ---
   #
   # In production mode:
   #  - enable help-doc                 - default false (enable/disable help-doc system)
   #  - enable table of contents (TOC)  - default false (enable/disable the generation table of contents when creating a markdown)
   #  - enable append data              - default false (enable/disable add data to parts if found in different files)
   #  - enable heading links            - default false (enable/disable heding IDs and links)
   #
   # In development mode:
   #  - enable help-doc                 - default false (enable/disable help-doc system)
   #  - enable table of contents (TOC)  - default false (enable/disable the generation table of contents when creating a markdown)
   #  - enable append data              - default false (enable/disable add data to parts if found in different files)
   #  - enable heading links            - default false (enable/disable heding IDs and links)
   #
   #FLY_CUBE_PHP_ENABLE_HELP_DOC: true
   #FLY_CUBE_PHP_ENABLE_HELP_DOC_TOC: true
   #FLY_CUBE_PHP_ENABLE_HELP_DOC_APPEND_DATA: true
   #FLY_CUBE_PHP_ENABLE_HELP_DOC_HEADING_LINKS: true
   ```

Auto Loader config
------------------

 * After checking the project for missing files, the configuration file 'config/autoload.json' will be added.

   ### Auto Loader JSON Settings ('config/autoload.json')

   ```json
   {
      "dirs": [
      ],
   
      "libs": {
      }
   }
   ```
   
   where:
    * "dirs" - an array of additional paths in which the search for the required resources will be performed;
    * "libs" - hash of prefixes of NameSpaces of libraries and their directories located in the project.

   By default, FlyCubePHP includes the following directories for searching:
    * vendor/
    * vendor/Twig-2.x/
    * vendor/JShrink/JShrink/
    * vendor/Psr/
    * vendor/Monolog/Monolog/
   
   ### Example Auto Loader JSON Settings ('config/autoload.json')
   
   ```json
   {
      "dirs": [
        "vendor",
        "vendor/Psr"
      ],
   
      "libs": {
        "cebe\\markdown": "vendor/Markdown"
      }
   }
   ```

Asset Pipeline
--------------

 * Starting from version 1.6.0, the names of the methods for the Asset Pipeline have been changed:

 Before:
 ```php
 // Get list of added JavaScript directories
 AssetPipeline::instance()->jsDirs();
 
 // Add JavaScript directory 
 AssetPipeline::instance()->appendJSDir($dir);
 
 // Get list of added Stylesheet directories
 AssetPipeline::instance()->cssDirs();
 
 // Add Stylesheet directory
 AssetPipeline::instance()->appendCSSDir($dir);
 ```

 After:
 ```php
 // Get list of added JavaScript directories
 AssetPipeline::instance()->javascriptDirs();
 
 // Add JavaScript directory 
 AssetPipeline::instance()->appendJavascriptDir($dir);
 
 // Get list of added Stylesheet directories
 AssetPipeline::instance()->stylesheetDirs();
 
 // Add Stylesheet directory
 AssetPipeline::instance()->appendStylesheetDir($dir);
 ```
