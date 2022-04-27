UPGRADE FROM 1.7.0 to 1.7.1
=================================

Action Cable config
-------------------

* Added adapter ipc socket mode in the configuration file 'config/cable.json' (default: 0755).

  ### Action Cable JSON Settings ('config/cable.json')

  ```json
  {
    "default_ipc_dev": {
      "adapter": "ipc",
      "adapter_socket": "/tmp/fly_cube_php_development.soc",
      "adapter_socket_mode": "0755",
      "server_host": "127.0.0.1",
      "server_port": 8000,
      "server_workers": 1
    },

    "default_ipc_prod": {
      "adapter": "ipc",
      "adapter_socket": "/tmp/fly_cube_php_production.soc",
      "adapter_socket_mode": "0755",
      "server_host": "127.0.0.1",
      "server_port": 8000,
      "server_workers": 5
    },

    "default_redis_dev": {
      "adapter": "redis",
      "redis_host": "127.0.0.1",
      "redis_port": 6379,
      "redis_password": "",
      "redis_channel": "fly_cube_php_development",
      "server_host": "127.0.0.1",
      "server_port": 8000,
      "server_workers": 1
    },

    "default_redis_prod": {
      "adapter": "redis",
      "redis_host": "127.0.0.1",
      "redis_port": 6379,
      "redis_password": "",
      "redis_channel": "fly_cube_php_production",
      "server_host": "127.0.0.1",
      "server_port": 8000,
      "server_workers": 5
    },

    "production": "default_redis_prod",
    "development": "default_ipc_dev"
  }
  ```

RouteCollector
--------------

* Starting from version 1.7.1, in function currentRouteUri added input argument 'bool $withParams = false':

Before:
 ```php
 // 
 // static public function currentRouteUri(): string {...}
 // 

 // Get current route URL (without input params)
 RouteCollector::currentRouteUri();
 ```

After:
 ```php
 // 
 // static public function currentRouteUri(bool $withParams = false): string {...}
 // 

 // Get current route URL (without input params)
 RouteCollector::currentRouteUri();
 // or
 RouteCollector::currentRouteUri(false);
 
 // Get current route URL (with input params)
 RouteCollector::currentRouteUri(true);
 ```

CoreHelper
----------

* Starting from version 1.7.1, in function scanDir arguments changed:

Before:
 ```php
 // 
 // static public function scanDir(string $dir, bool $recursive = false, bool $appendDirs = false): array {...}
 // 

 // Recursive scan directory
 CoreHelper::scanDir('/', true, true);
 ```

After:
 ```php
 // 
 // static public function scanDir(string $dir, array $args = []): array {...}
 // 
 // Supported input arguments:
 // 
 // - [bool] recursive       - use recursive scan (default: false)
 // - [bool] append-dirs     - add subdirectories (default: false)
 // - [bool] only-dirs       - search only directories (default: false)
 //

 // Recursive scan directory
 CoreHelper::scanDir('/', [ 'recursive' => true, 'append-dirs' => true ]);
 ```
