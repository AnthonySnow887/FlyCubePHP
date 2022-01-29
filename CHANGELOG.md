# 1.5.0 (29.01.2022)

 * Update Development Guide RUS
 * Add required PHP modules: 
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
