# 1.8.0 (31.05.2022)

 * Update Development Guide RUS
 * Update FlyCubePHP requires: SCSS-PHP -> v1.10.2
 * Add support for multiple databases
 * Fix BaseActionController (fix render if set arg 'skip_render')
 * Update RouteCollector:
   * add constraints to use automatic regular expression validation for the dynamic segment. Example:
     ```php
     get('/photos/:id', [ 'to' => 'Photos#show', 'constraints' => [ 'id' => '/[A-Z]\d{5}/' ] ] );
     ```
   
   * add redirect to routes to use automatic redirect without calling a controller method. Examples:
     ```php
     get("/stories", [ 'to' => redirect("/articles") ] );
     // --- or ---
     get("/stories/:name", [ 'to' => redirect("/articles/%{name}") ] );
     // --- or ---
     get("/stories/:name", [ 'to' => redirect("/articles/%{name}", /*status*/ 302) ] );
     ```
 
   * add route globbing and wildcard segments.
   
     Route globbing is a way to specify that a particular parameter should be matched to all the remaining parts of a route. For example:
     ```php
     get('photos/*other', [ 'to' => 'Photos#unknown' ]);
     ```
     
     This route would match ```photos/12``` or ```/photos/long/path/to/12```, setting ```_params['other'] to "12" or "long/path/to/12"```. 
   
     The segments prefixed with a star are called "wildcard segments". Wildcard segments can occur anywhere in a route. For example:
     ```php
     get('books/*section/:title', [ 'to' => 'Books#show' ]);
     ```
     
     would match ```books/some/section/last-words-a-memoir``` with ```_params['section'] equals 'some/section'```, and ```_params['title'] equals 'last-words-a-memoir'```.
     
     Technically, a route can have even more than one wildcard segment. The matcher assigns segments to parameters in an intuitive way. For example:
     ```php
     get('*a/foo/*b', [ 'to' => 'Test#index' ]);
     ```
     
     would match ```zoo/woo/foo/bar/baz``` with ```_params['a'] equals 'zoo/woo'```, and ```_params['b'] equals 'bar/baz'```.
   
 * Update AssetPipeline/CSSBuilder:
   * add flag FLY_CUBE_PHP_ENABLE_SCSS_LOGGING
   * add SCSSLogger
 * Update AssetPipeline:
   * add support *.ico
   * add javascriptFilePathReal
     ```php
     /**
     * Получить физический путь (список путей) для JS файлов
     * @param string $name
     * @return array|string
     * @throws
     *
     * === Example
     *
     *   javascriptFilePathReal('application')
     *   * => app/assets/javascripts/application.js
     */
     public function javascriptFilePathReal(string $name)/*: string|array*/ {...}
     ```
     
   * add stylesheetFilePathReal
     ```php
     /**
     * Получить физический путь (список путей) для CSS файлов
     * @param string $name
     * @return array|string
     * @throws
     *
     * === Example
     *
     *   stylesheetFilePathReal('application')
     *   * => app/assets/stylesheets/application.css
     */
     public function stylesheetFilePathReal(string $name)/*: string|array*/ {...}
     ```
     
   * add imageFilePathReal
     ```php
     /**
     * Поиск физического пути до image файла по имени
     * @param string $name
     * @return string
     * @throws
     *
     * === Example
     *
     *   imageFilePathReal("configure.svg")
     *   * => app/assets/images/configure.svg
     */
     public function imageFilePathReal(string $name): string {...}
     ```
     
 * Update templates/bin/fly_cube_php
   * update assetsPrecompile with checking route redirects 
   * update appRoutes with checking route redirects

 * Update HelperClasses/CoreHelper:
   * add spliceSymbolFirst
     ```php
     /**
      * Обрезать символ вначале
      * @param string $str - строка
      * @param string $symbol - удаляемый символ
      * @return string
      *
      * echo spliceSymbolFirst("/tmp/app1/", "/");
      *   => "tmp/app1/"
      */
     static public function spliceSymbolFirst(string $str, string $symbol): string {...}
     ```
     
   * add spliceSymbolLast
     ```php
     /**
      * Обрезать символ вконце
      * @param string $str - строка
      * @param string $symbol - удаляемый символ
      * @return string
      *
      * echo spliceSymbolLast("/tmp/app1/", "/");
      *   => "/tmp/app1"
      */
     static public function spliceSymbolLast(string $str, string $symbol): string {...}
     ```
     
 * Update controllers helpers:
   * refactoring (other options will be added as tag attributes)
   * update JavascriptTagHelper (add javascript_asset_tag)
     ```php
     /**
      * Добавить тэг скрипта с содержимым файла
      * @param string $name
      * @param array $options
      * @return string
      *
      * ==== Options
      *
      * - type   - Set script type
      * - nonce  - Enable/Disable nonce tag for script (only true/false) (default: false)
      *
      * NOTE: other options will be added as tag attributes.
      * NOTE: for use nonce - enable CSP Protection.
      * NOTE: this is overload function for 'javascript_tag(...)'.
      * NOTE: all dependent scripts are also built into the body of the block.
      *
      * ==== Examples
      *
      *   javascript_content_tag("application")
      *   * => <script>
      *        //<![CDATA[
      *        //
      *        // Created by FlyCubePHP generator.
      *        //
      *        //
      *        // This is a manifest file that'll be compiled into application.js, which will include all the files
      *        // listed below.
      *        //
      *        // Any JavaScript/JavaScript.PHP file within this directory, lib/assets/javascripts, or any plugin's
      *        // vendor/assets/javascripts directory can be referenced here using a relative path.
      *        //
      *        // Supported require_* commands:
      *        // require [name]       - load file (search by name without extension)
      *        // require_tree [path]  - load all files from path
      *        //
      *        //]]>
      *        </script>
      */
     public function javascript_content_tag(string $name, array $options = []): string {...} 
     ```
       
   * update StylesheetTagHelper (add stylesheet_asset_tag)
     ```php
     /**
      * Добавить тэг скрипта с содержимым файла стилей
      * @param string $name
      * @param array $options
      * @return string
      *
      * ==== Options
      *
      * - nonce  - Enable/Disable nonce tag for script (only true/false) (default: false)
      *
      * NOTE: other options will be added as tag attributes.
      * NOTE: for use nonce - enable CSP Protection.
      * NOTE: this is overload function for 'stylesheet_tag(...)'.
      * NOTE: all dependent scripts are also built into the body of the block.
      *
      * ==== Examples
      *
      *   stylesheet_content_tag("application")
      *   * => <script type="text/css">
      *        //<![CDATA[
      *        // Created by FlyCubePHP generator.
      *        //
      *        // This is a manifest file that'll be compiled into application.css, which will include all the files
      *        // listed below.
      *        //
      *        // Any CSS and SCSS file within this directory, lib/assets/stylesheets, or any plugin's
      *        // vendor/assets/stylesheets directory can be referenced here using a relative path.
      *        //
      *        // Supported require_* commands:
      *        //
      *        // require [name]       - load file (search by name without extension)
      *        // require_tree [path]  - load all files from path
      *        //
      *        //]]>
      *        </script>
      */
     public function stylesheet_content_tag(string $name, array $options = []): string {...}
     ```
       
 * Code refactoring
 * Fix comments



# 1.7.1 (27.04.2022)

 * Update Development Guide RUS
 * Fix AssetPipeline/CSSBuilder (fix scss import path)
 * Fix fly_cube_php bin (fix create new project)
 * Update BaseActionController:
   * update render function:
     * add params: view, args
     
     ```php
     /**
      * Метод отрисовки
      * @param string $action
      * @param array $options - дополнительные настройки отрисовки
      * @throws
      *
      * ==== Options
      *
      * - [bool]     layout_support  - отрисовывать или нет базовый слой (default: true)
      * - [string]   layout          - задать базовый layout (должен раполагаться в каталоге: app/views/layouts/)
      * - [string]   view            - задать view для отрисовки (view текущего метода контроллера, если он существует, будет проигнорирован)
      * - [array]    args            - задать массив аргументов, который будет передан в Twig при рендеринге
      * - [bool]     skip_render     - пропустить отрисовку страницы
      *
      * NOTE: Key 'render_action' in args array is reserved!
      */
     final public function render(string $action = "", array $options = [ 'layout_support' => true ]) {...}
     ```
     
     * add other views paths
   * add core layouts namespaces
   * add controller layouts namespaces

 * Fix BaseActionControllerAPI (add abstract)
 * Fix FlyCubePHPEnv.php (add app views paths)
 * Update RouteCollector:
   * add function serverHost
  
     ```php
     /**
      * Получить адрес текущего сервера
      * @return string
      */
     static public function serverHost(): string {...}
     ```

   * add function serverPort
  
     ```php
     /**
      * Получить порт текущего сервера
      * @return int
      */
     static public function serverPort(): int {...}
     ```
    
   * update function currentRouteUri (add input argument 'bool $withParams = false')

     ```php
     /**
      * Получить текущий URL маршрута
      * @param bool $withParams - удалить из маршрута аргументы или нет
      * @return string
      */
     static public function currentRouteUri(bool $withParams = false): string {...}
     ```

 * Update Web Sockets:
   * add adapter ipc socket mode in cable.json (default: 0755)
  
     ```json
     {
       "default_ipc_dev": {
         "adapter": "ipc",
         "adapter_socket": "/tmp/fly_cube_php_development.soc",
         "adapter_socket_mode": "0755",
         "server_host": "127.0.0.1",
         "server_port": 8000,
         "server_workers": 1
       }
     }
     ```

 * Update CoreHelper:
   * update function scanDir (function arguments changed) (see UPGRADE-1.7.1 how to upgrade to the latest version)

     ```php
     /**
      * Сканирование каталога
      * @param string $dir
      * @param array $args - массив параметров сканирования
      * @return array
      *
      * ==== Args
      *
      * - [bool] recursive       - use recursive scan (default: false)
      * - [bool] append-dirs     - add subdirectories (default: false)
      * - [bool] only-dirs       - search only directories (default: false)
      */
     static public function scanDir(string $dir, array $args = []): array {...}
     ```

 * Code refactoring


# 1.7.0 (25.03.2022)

 * Update Development Guide RUS
 * Add required PHP modules:
    * php7-APCu

 * Update loading speed optimization:
   >
   > NOTE:
   > with enabled opcode caching mechanism OPCache and APCu caching of the FlyCubePHP core in production mode,
   > the speed of loading web pages is increased by 18%-25%.
   >

 * Added APCu cache system
 * Fix fly_cube_php bin:
   * add AutoLoader
   * show config errors in '--assets-precompile'
   * fix upgrade function (replaceProjectDefBinary)
   
 * Fix RouteStreamParser (fix get input int value '0')
 * Fix Route alias (now value is generated from route URL)
 * Update RouteCollector:
   * update function 'allRoutes' - add argument 'bool  = false'
     
     ```php
     public function allRoutes(bool $sort = false): array {...}
     ```
   
 * Update AutoLoader (add autoload path 'vendor/FlyCubePHP/')
 * Update Config 
   * add function hasErrors
     
     ```php
     public function hasErrors(): bool {...}
     ```

   * add function errors

     ```php
     public function errors(): array {...}
     ```
   
   * add config settings:
     * FLY_CUBE_PHP_ASSETS_CACHE_MAX_AGE_SEC
     * FLY_CUBE_PHP_ENABLE_APCU_CACHE
     * FLY_CUBE_PHP_PREPARE_ASSETS_REQUIRES_LIST
     
 * Fix AssetPipeline (fix error net::ERR_CONTENT_LENGTH_MISMATCH 200 (OK) in browser)
 * Update AssetPipeline:
   * update loading speed optimization 
   * add prepare assets requires list
     > 
     > NOTE: Use flag 'FLY_CUBE_PHP_PREPARE_ASSETS_REQUIRES_LIST'.
     > 
     > This flag allows you to 'insert' at the beginning of the list of dependencies libraries located in 'lib/assets' and 'vendor/assets'.
     > 
     > The dependency tree does not break.
     > 
     
   * added support for APCu cache

 * Update ApiDocObject:
   * add function isEmpty
   
     ```php
     public function isEmpty(): bool {...}
     ```
     
 * Added support HTTP HEAD (added check in BaseControllers and RouteCollector)

 * Update ApiDoc:
   * update loading speed optimization 
   * added support for APCu cache

 * Update HelpDoc:
   * update loading speed optimization
   * added support for APCu cache
 
 * Update ComponentsCore: 
   * update loading speed optimization
   * added support for APCu cache
   * remove unused includes

 * Code refactoring
 * Added 'test_page_load.sh'
 
   ```shell
   FlyCubePHP/tests> sh ./test_page_load.sh -h
    
   Help:
    
   --help - show this help (-h)
    
   Input args:
   1 - host (if empty - used 127.0.0.1:8080)
   2 - application URL prefix (may be empty)
   3 - number of query iterations (may be empty; default: 10)
    
   Examples:
   #> sh ./test_page_load.sh
   
   #> sh ./test_page_load.sh 127.0.0.1:8080 my-project
   
   #> sh ./test_page_load.sh 127.0.0.1:8080 my-project 5
   
   #> sh ./test_page_load.sh 127.0.0.1:8080 "" 5
   
   #> sh ./test_page_load.sh "" "" 5
   ```
   Example usage:
   ```shell
   FlyCubePHP/tests> sh ./test_page_load.sh 127.0.0.1:8080 my-project 5
   LOAD: 0.32 sec (for iteration 1)
   LOAD: 0.31 sec (for iteration 2)
   LOAD: 0.31 sec (for iteration 3)
   LOAD: 0.39 sec (for iteration 4)
   LOAD: 0.30 sec (for iteration 5)
   -----------------------------------
   TOTAL: 1.63 sec (for 5 iterations)
     AVG: 0.326 sec
   ```

# 1.6.0 (17.02.2022)

 * Update Development Guide RUS
 * Add HelpDoc support:
   >
   > NOTE: For more information see the Development Guide.
   >

   * Starting with FlyCubePHP 1.6.0, creation of help files based on the Markdown format is supported. 
     FlyCubePHP Help Doc core supports the following functionality:
     * automatic search, loading and parsing of help files in directories:
       * 'doc/help/'
       * 'plugins/*/doc/help/'
     * automatic search and download of images for help files in directories:
       * 'doc/help/images/'
       * 'plugins/*/doc/help/images/'
     * converting parsed help files into a single Markdown document with automatic result caching.

     The main task of the FlyCubePHP Help Doc core is to combine scattered help documents into a single file.
     This takes into account the hierarchy of Headings, the union of their subsections,
     as well as data in case of duplication in different documents (NOTE: this functionality can be enabled or disabled
     in the project configuration file with the 'FLY_CUBE_PHP_ENABLE_HELP_DOC_APPEND_DATA' flag). 
     Files are loaded in the order they are located in the search directories.
     Support for built-in helper functions greatly simplifies the creation of help files.

 * Update ApiDoc:
   >
   > NOTE: For more information see the Development Guide.
   >

   * added helper functions:
     * current_action_url - get current controller action URL
     * action_url - get URL by controller and action

 * Add TemplateCompiler:
   >
   > NOTE: For more information see the Development Guide.
   >

   * Starting from version FlyCubePHP 1.6.0, a simple templates compiler has been added to the core of the framework. 
     This tool allows you to parse various text files that have helper function inserts in their content, 
     similar to those used in the Twig template engine, and replace them with the result of executing the specified functions.
   
     Using these functions is similar to using Twig template functions:
     * {{ FUNCTION (ARGS) }} - this expression will be replaced with the result of executing the function specified in the expression line;
     * {# FUNCTION (ARGS) #} - this expression will be replaced with an empty string; the function call will be skipped.

 * Update fly_cube_php bin:
   * update '--assets-precompile' command:
     * add compile JavaScripts, Stylesheets, Images
     * add rendering (or skip rendering) for graphics controllers and their page templates (command: '--skip-rendering=[VALUE]')
   * add '--assets-list' command
   * add filter commands for '--assets-list':
     * '--javascripts=[VALUE]' - JavaScript assets display filter (optional; default: true)
     * '--stylesheets=[VALUE]' - Stylesheet assets display filter (optional; default: true)
     * '--images=[VALUE]' - Image assets display filter (optional; default: true)

 * Fix fly_cube_php bin (fixed reset of base error handler)
 * Update AutoLoader:
   * add load settings from file (config/autoload.json); 
   * add method 'appendAutoLoadLib(string $libRootNamespace, string $libRootDir) {...}'

 * Update AssetPipeline:
   * change methods (see UPGRADE-1.6.0 how to upgrade to the latest version):
     * 'jsDirs' to 'javascriptDirs'
     * 'appendJSDir' to 'appendJavascriptDir'
     * 'cssDirs' to 'stylesheetDirs'
     * 'appendCSSDir' to 'appendStylesheetDir'
   * added methods :
     * javascriptList - get list of loaded javascript assets and their paths
     * stylesheetList - get list of loaded stylesheet assets and their paths

 * Fix AssetPipeline (fixed directory name generation based on hashes for cached files)
 * Update ErrorHandlingCore:
   * added methods:
     * freezeErrorHandler - block error handler installation
     * isErrorHandlerFreeze - checking is the error handler setup blocked?
   * small refactoring

 * Update RouteCollector (added method 'applicationUri')
 * Update ComponentsCore:
   * added help-doc loading for plugins
   * small refactoring 

 * Update WSServiceApplication (added by calling the 'freezeErrorHandler' method for the service handler)
 * Update templates:
   * application_env.conf.tmpl (add Help Doc settings)
   * cable.json.tmpl (small refactoring)
   
 * Add templates:
   * autoload.json.tmpl (AutoLoader settings template)
   
 * Fix comments

# 1.5.1 (04.02.2022)

 * Update Development Guide RUS
 * Update fly_cube_php bin:
   * added display of information about current env mode
   * add skip hidden files in deleteDirFiles
   
 * Update RouteCollector (add currentRouteIsRoot)
 * Update JSBuilder (add [ 'flaggedComments' => false ] when build *.min.js (i.e. assembly without comments))
 * Fix Web Sockets:
   * IPC adapter (fix intermittent incorrect data sending (blocking socket type set))
   * Redis adapter (added exception checking and error handling)
   * add load initializers
   * fix cookie decode
   
 * Fix Session (fix decode default php session)
 * Fix HttpResponse (fix cookie decode)

# 1.5.0 (29.01.2022)

 * Update Development Guide RUS
 * Add required PHP modules:
   * php7-pcntl
   * php7-redis
   * php7-sockets
   
 * Add Action Cable Web Sockets support:
   * add FlyCubePHP Web Sockets Service
   * add base server files & classes
   * add base client files
   * add supported adapters:
     * IPC
     * Redis
   * add ActionCableHelper class
   * update fly_cube_php bin (add command '--channel' for create new Action Cable Channel)

 * Update fly_cube_php bin:
   * small code refactoring
   * add command '--channel' for create new Action Cable Channel
   * change command '--check-dirs' to '--check-project'

 * Update AssetTagHelper:
   * change helper methods (see UPGRADE-1.5.0 how to upgrade to the latest version):
     * 'add_plugin_view_javascripts' to 'add_view_javascripts'
     * 'add_plugin_view_stylesheets' to 'add_view_stylesheets'
   * add helper methods:
     * current_plugin_directory
     * plugin_controller_stylesheets_file
     * plugin_controller_javascript_file
     * plugin_controller_action_javascript_file

 * Update templates:
   * application_env.conf.tmpl (add Action Cable settings)
   * application.html.tmpl (add code for load plugins JS/CSS files)
   * apache24.conf.tmpl (add web sockets support)

 * Add templates:
   * ws-server.service.tmpl (systemd service template)
   * cable.json.tmpl (Action Cable config)
   * cable.js.tmpl (Action Cable base JS file)
   * channel_base.php.tmpl (Action Cable base php class for project)
   * channel.js.tmpl (Action Cable js channel class)
   * channel.php.tmpl (Action Cable php channel class)

 * Update ActiveRecord (add modelGlobalID)

 * Update Session:
   * add read-only state
   * add decode default php session data
   * add encode default php session data

 * Update RouteCollector:
   * refactoring (changed the order of the functions in the class)
   * add currentHostProtocol
   * add currentClientPort
   * add currentClientBrowser
   * add browserPlatform
   * add browserVersion
  
 * Update Config.php (add Action Cable defines)
 * Fix Logger (fix get TAG_LOG_ROTATE_FILE_DATE_FORMAT & TAG_LOG_ROTATE_FILE_NAME_FORMAT)
 * Fix comments
 
# 1.4.0 (21.12.2021)

 * Update Development Guide RUS 
 * Add required PHP module: zlib
 
 * Add ApiDoc:
   * load api-doc files from json
   * build support in markdown
   * support for caching compiled markdown api files
   * update fly_cube_php bin (add build api-doc json for api controllers)

 * Update AssetPipeline:
   * add check http header 'If-None-Match'
   * add assets compression: 
     * gzip (default)
     * deflate
   * add check url prefix 'assets/' for input request
   * update CSSBuilder:
     * enable scss minifier in production 
     * add check file ext and remove in '= require ...'
     * fix lost '= require ...' sections in production mode in scss files
     * add css minifier and it is enabled in production mode
   * update JSBuilder:
     * add check file ext and remove in '= require ...'
   * NOTE: with enabled compression of compiled assets and checking ETag in production mode, 
     the speed of loading web pages is increased by 25%.

 * Fix fly_cube_php bin (fix output if create/copy/delete files and dirs failed)
 * Fix Controllers/Extensions/NetworkBase (fix invalid data size for send -> used strlen)
 * Update Controllers/Helpers/AssetTagHelper (update link_to [add params])
 * Update HelperClasses/CoreHelper (add arrayIsList)
 * Code refactoring

# 1.3.0 (01.12.2021)

 * Update Development Guide RUS
 * Update fly_cube_php bin file:
   * fix --assets-precompile
   * fix backtrace log trim
   * add --check-dirs
   * add select latest version from GitHub
   * add upgrade project & force upgrade
 * Update routes.php.tmpl (update comments)
 * Update Cookie (set and delete cookies with a name equal to session_name() is forbidden)
 * Update AssetPipeline (add clear all php buffers before send data)
 * Update Controllers/Extensions/NetworkBase trait (add clear all php buffers before send data)
 * Update BaseController:
   * remove include HttpCodes.php 
   * add evalException method
   * add check before/after actions return value
 * Update BaseActionController:
   * fix invalid send data from gui controller in production mode 
   * add check before/after actions return value
   * add clear all php buffers before send data
 * Update BaseActionControllerAPI:
   * add check before/after actions return value
   * add clear all php buffers before send data
 * Update ActiveRecord:
   * select pkey after insert new item
   * add callbacks
   * add readOnly flag
   * fix comments
 * Update Config:
   * add method keys 
   * add method args
 * Add Network/HttpClient
 * Move HttpCodes.php from src/HelperClasses/ to src/Network/
 * Update ErrorHandlingCore:
   * fix backtrace log trim
   * add clear all php buffers before send data
 * Fix RequestForgeryProtection (add urlencode/urldecode for csrf token)
 * Change error report:
   * E_WARNING disabled 
   * E_NOTICE disabled
   * E_USER_WARNING disabled 
   * E_USER_NOTICE disabled
 * Update FlyCubePHP.php (add try-catch for call controller evalException)
 * Update FlyCubePHPEnvLoader.php (add check application secret.key file: if not found -> development mode)
 * Code refactoring

# 1.2.0 (22.11.2021)

 * Update Development Guide RUS
 * Update BaseActionController:
   * add Twig global variable 'router'
   * add skipRenderForActions
   * add skipRenderForAction
   * add appendHelperMethod
   * add isHelperMethod
   * add property 'skip_render' in function render
 * Update BaseController (skipBeforeAction skipAfterAction input array support).
 * Fix HttpCodes (fix codeInfo method).
 * Update FlyCubePHP.php (add render 404 page if it's given).
 * Update fly_cube_php bin file (show route 'as').

 * Update Route core:
   * update all routes method (see UPGRADE-1.2.0 how to upgrade to the latest version)
   * add routes with args: '/test/:id' or '/test?id=123'
   * update Route:
     * add tag 'as'
     * fix uri method (remove input args) 
     * add uriFull with args
   * update RouteCollector:
     * add throw error if found invalid route
     * add currentRouteArg

 * Small refactoring ComponentsCore
 * Update Logger (add prepareContext for hide password value in log)

 * Update ActiveRecord:
   * add password functions
   * add setColumnMappings

 * Fix EncryptedCookieBuilder (add trigger_error if encrypt failed)
 * Fix Migration / SchemaDumper:
   * fix dumpSchema
   * fix dumpTable default column value

 * Fix PostgreSQL migrator:
   * fix select primary keys
   * fix drop table

# 1.1.2 (16.11.2021)

 * Fix Route Collector (fixed error in parsing input data for post/put/patch/delete requests)

# 1.1.1 (12.11.2021)

 * Update fly_cube_php binary (add check for plugin-create)
 * Update dependencies version (JShrink -> v1.4.0; ScssPhp -> v1.8.1)
 * Fix load needed library (invalid autoloader include)
 * Fix JS/CSS builders (fix multiline comments errors)
 * Update BaseController (add controllerClassName + fix controllerName)
 * Update BaseActionController + BaseActionControllerAPI (add _params['controller-class'])
 * Fix Components Manager (fix dependency tree load errors)
 * Fix Route Collector (fix http headers names)
 * Update ActiveRecord (add column mapping)

# 1.1.0 (08.11.2021)

 * Add MySQL support (database adapter + migrator)
 * Fix SQLite migrator (error while creating an existing index)
 * Fix Logger (fail if not found log folder)
 * Fix Default error page (output multiple unreadable errors)
 * Fix Asset Pipeline (fix send svg files)
 * Fix Components Manager

# 1.0.1 (31.10.2021)

 * Fix Config loader (fail if value is empty)  
 * Fix JS/CSS builder (fail if load invalid files [not *.js/.*css])
 * Fix invalid current route uri (change str_replace to substr_replace) 

# 1.0.0 (20.10.2021)

 * Release first version.
