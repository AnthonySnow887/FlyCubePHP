UPGRADE FROM 1.6.0 to 1.7.0
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

 * The following sections have been added (or changed) to the project configuration file ('config/application_env.conf').
   The absence of these sections (or their parameters) in the configuration file will tell the FlyCubePHP core to use the default values.

   ### Cache Settings
   
   * Added parameter "FLY_CUBE_PHP_ENABLE_APCU_CACHE"

   #### Example of the entire configuration section

   ```
   #
   # --- cache settings ---
   #
   # NOTE: disable this settings in production mode!
   #
   # In production mode:
   #  - rebuild cache      - default false
   #  - rebuild twig cache - default false
   #  - enable apcu cache  - default true
   #
   # In development mode:
   #  - rebuild cache      - default true
   #  - rebuild twig cache - default true
   #  - enable apcu cache  - default false
   #
   #FLY_CUBE_PHP_REBUILD_CACHE: false
   #FLY_CUBE_PHP_REBUILD_TWIG_CACHE: false
   #FLY_CUBE_PHP_ENABLE_APCU_CACHE: false
   ```

   ### Asset Pipeline Settings

   * Added parameters:
     * "FLY_CUBE_PHP_ASSETS_CACHE_MAX_AGE_SEC"
     * "FLY_CUBE_PHP_PREPARE_ASSETS_REQUIRES_LIST"

   #### Example of the entire configuration section

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
   #  - cache max age sec              - default 31536000 (set HTTP header Cache-Control max-age value)
   #  - prepare assets requires list   - default true (This flag allows you to 'insert' at the beginning of the list
   #                                                   of dependencies libraries located in 'lib/assets' and 'vendor/assets'.
   #                                                   The dependency tree does not break.)
   #
   # In development mode:
   #  - enable compression             - default false (enable/disable assets compression)
   #  - compression type               - default gzip (set assets compression type)
   #  - cache max age sec              - default 31536000 (set HTTP header Cache-Control max-age value)
   #  - prepare assets requires list   - default true (This flag allows you to 'insert' at the beginning of the list
   #                                                   of dependencies libraries located in 'lib/assets' and 'vendor/assets'.
   #                                                   The dependency tree does not break.)
   #
   #FLY_CUBE_PHP_ENABLE_ASSETS_COMPRESSION: true
   #FLY_CUBE_PHP_ASSETS_COMPRESSION_TYPE: gzip
   #FLY_CUBE_PHP_ASSETS_CACHE_MAX_AGE_SEC: 31536000
   #FLY_CUBE_PHP_PREPARE_ASSETS_REQUIRES_LIST: true
   ```
