# 1.2.0 (22.11.2021)

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
