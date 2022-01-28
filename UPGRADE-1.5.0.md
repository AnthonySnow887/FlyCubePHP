UPGRADE FROM 1.4.0 to 1.5.0
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

   ### Action Cable Settings
   
   ```
   #
   # --- action cable settings ---
   #
   # In production mode:
   #  - mount path                     - default '/cable' (set Action Cable mount path)
   #  - enable perform                 - default true (enable/disable remote method call of channel classes)
   #
   # In development mode:
   #  - mount path                     - default '/cable' (set Action Cable mount path)
   #  - enable perform                 - default true (enable/disable remote method call of channel classes)
   #
   #FLY_CUBE_PHP_ACTION_CABLE_MOUNT_PATH: /cable
   #FLY_CUBE_PHP_ACTION_CABLE_ENABLE_PERFORM: true
   ```

Action Cable config
-------------------

 * After checking the project for missing files, the configuration file 'config/cable.json' will be added.

   ### Action Cable JSON Settings ('config/cable.json')

   ```json
   {
     "default_ipc_dev": {
       "adapter": "ipc",
       "adapter_socket": "/tmp/fly_cube_php.soc",
       "server_host": "127.0.0.1",
       "server_port": 8000,
       "server_workers": 1
     },
   
     "default_ipc_prod": {
       "adapter": "ipc",
       "adapter_socket": "/tmp/fly_cube_php.soc",
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
   
     "production": "default_ipc_prod",
     "development": "default_redis_dev"
   }
   ```

Gui Controller Asset-Tag-Helper
-------------------------------

 * Starting from version 1.5.0 Twig helper methods 'add_plugin_view_javascripts' and 'add_plugin_view_stylesheets' 
   have been replaced by 'add_view_javascripts' and 'add_view_stylesheets'.

 Before (Twig notation):
 ```html
 {{ add_plugin_view_javascripts("test_2") }}

 {{ add_plugin_view_stylesheets("test_2") }}
 ```

 After (Twig notation):
 ```html
 {{ add_view_javascripts("test_2") }}

 {{ add_view_stylesheets("test_2") }}
 ```

Web Sockets Support
-------------------

 * Starting from version 1.5.0, support for web sockets has been added. t
   They use a separate service 'FlyCubePHP Web Sockets Service' ([MyProhject/bin/fly_cube_php_ws_server]).

### Recommendations for configuring and running FlyCubePHP Web Sockets Server

1. Check the username and group under which your web server is running, 
   and also correct the paths in the commands to start the WebSockets server 
   in the systemd service file “[MyProject]/my_project.ws_server.service”

   ```ini
   # 
   # Created by FlyCubePHP generator.
   # User: test
   # Date: 21.01.22
   # Time: 10:58
   # 
   
   [Unit]
   Description=FlyCubePHP WebSockets Service for [MyProject]
   After=syslog.target
   After=network.target
   
   [Service]
   Type=forking
   PrivateTmp=false
   
   # Web server user and group (default for Apache on OpenSUSE):
   User=wwwrun
   Group=wwwrun
   
   # Exec commands:
   ExecStart=/opt/MyProject/bin/fly_cube_php_ws_server --start
   ExecStop=/opt/MyProject/bin/fly_cube_php_ws_server --stop
   ExecReload=/opt/MyProject/bin/fly_cube_php_ws_server --restart
   
   # The server might be slow to stop, and that's fine. Don't kill it
   SendSIGKILL=no
   Restart=on-failure
   
   [Install]
   WantedBy=multi-user.target
   ```

2. Copy the WebSockets server service file to the systemd services directory (in OpenSUSE: "/usr/lib/systemd/system/"):

   ```bash
   MyProject> sudo cp my_project.ws_server.service /usr/lib/systemd/system/
   ```

3. Start the WebSockets server service:

   ```bash
   #> sudo systemctl start my_project.ws_server.service
   ```

4. Perform a WebSockets Server Health Check:

   ```bash
   #> sudo systemctl status my_project.ws_server.service

   ● my_project.ws_server.service - FlyCubePHP WebSockets Service for [MyProject]
      Loaded: loaded (/usr/lib/systemd/system/my_project.ws_server.service; enabled; vendor preset: disabled)
      Active: active (running) since Thu 2022-01-27 18:18:44 MSK; 1s ago
      Process: 17297 ExecStop=/opt/MyProject/bin/fly_cube_php_ws_server --stop (code=exited, status=0/SUCCESS)
      Process: 17369 ExecStart=/opt/MyProject/bin/fly_cube_php_ws_server --start (code=exited, status=0/SUCCESS)
      Main PID: 17371 (php)
      Tasks: 11
      CGroup: /system.slice/my_project.ws_server.service
      ├─17371 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17375 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17376 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17377 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17378 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17379 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17380 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17381 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17382 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      ├─17383 php /opt/MyProject/bin/fly_cube_php_ws_server --start
      └─17384 php /opt/MyProject/bin/fly_cube_php_ws_server --start
   
      янв 27 18:18:44 test.my.com systemd[1]: Starting FlyCubePHP WebSockets Service for [MyProject]...
      янв 27 18:18:44 test.my.com fly_cube_php_ws_server[17369]: WSServiceApplication started
      янв 27 18:18:44 test.my.com systemd[1]: Started FlyCubePHP WebSockets Service for [MyProject].
   ```
