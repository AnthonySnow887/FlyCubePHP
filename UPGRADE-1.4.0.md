UPGRADE FROM 1.2.0/1.3.0 to 1.4.0
=================================

---
NOTE

After upgrading the project, you must clear the cache directory to avoid errors in client access to resource files.

Example command:
```bash
MyProject/bin> sudo ./fly_cube_php --clear-cache
```

---

Application config
------------------

 * The following sections have been added to the project configuration file ('config/application_env.conf').
   The absence of these sections in the configuration file will tell the FlyCubePHP core to use the default values.

   ### API-Doc Settings
   
   ```
   #
   # --- api doc settings ---
   #
   # In production mode:
   #  - enable api-doc                 - default false (enable/disable api-doc system)
   #
   # In development mode:
   #  - enable api-doc                 - default false (enable/disable api-doc system)
   #
   #FLY_CUBE_PHP_ENABLE_API_DOC: true
   ```
   
   ### Asset Pipeline Settings 
   
   ```
   #
   # --- asset pipeline settings ---
   #
   # Supported compression types:
   #  - gzip
   #  - deflate
   #
   # In production mode:
   #  - enable compression             - default true (enable/disable assets compression)
   #  - compression type               - default gzip (set assets compression type)
   #
   # In development mode:
   #  - enable compression             - default false (enable/disable assets compression)
   #  - compression type               - default gzip (set assets compression type)
   #
   # Supported compression types:
   #  - gzip
   #  - deflate
   #
   #FLY_CUBE_PHP_ENABLE_ASSETS_COMPRESSION: true
   #FLY_CUBE_PHP_ASSETS_COMPRESSION_TYPE: gzip
   ```

Assets
------

 * Starting from version 1.4.0 in directives '= require ...' and '= require_tree ...'
   you can specify the extension of dependent files.
 
### JS-file

 Before:
 ```js
 //
 //= require fly-cube-php-ujs
 //= require jquery-3.6.0.min
 //= require bootstrap-5.1.3.min
 //
 ```

 After:
 ```js
 //
 //= require fly-cube-php-ujs
 //= require jquery-3.6.0.min
 //= require bootstrap-5.1.3.min
 //

 or

 //
 //= require fly-cube-php-ujs.js
 //= require jquery-3.6.0.min.js
 //= require bootstrap-5.1.3.min.js
 //
 ```

### CSS/SCSS-file

 Before:
 ```css
 /*
  *= require bootstrap-5.1.3.min
  */
 ```

 After:
 ```css
 /*
  *= require bootstrap-5.1.3.min
  */

 or

 /*
  *= require bootstrap-5.1.3.min.css
  */
 ```

Gui Controller Asset-Tag-Helper
-------------------------------

* Added the ability to pass parameters to the helper method 'link_to(...)'.

Supported input arguments:
 - [string] controller     - Set controller name
 - [string] action         - Set controller action name
 - [string] href           - Set link URL
 - [string] method         - Set link HTTP method (get/post/put/patch/delete)
 - [string] class          - Set link class
 - [string] id             - Set link ID
 - [string] target         - Set link target
 - [string] rel            - Set link rel
 - [array]  params         - Set additional URL params
 
NOTE: 'href' and 'controller + action' are mutually exclusive arguments!

NOTE: for a more detailed description see the file 'src/Core/Controllers/Helpers/AssetTagHelper.php'.

 Before (Twig notation):
 ```html
 {{ link_to("Test Link", {"controller": "AppCore", "action": "test_with_id", "params": {"id":123} }) }}
 
 where:
  - AppCore::test_with_id -> GET (url with params: /test/:id)
   
 Result (invalid result URL, as the parameter array was ignored):
 
   <a href="/test/:id">Test Link</a>
 ```

 After (Twig notation):
 ```html
 {{ link_to("Test Link", {"controller": "AppCore", "action": "test_with_id", "params": {"id":123} }) }}
  
 where:
  - AppCore::test_with_id -> GET (url with params: /test/:id)
   
 Result (correct result URL):
   
   <a href="/test/123">Test Link</a>
 ```
