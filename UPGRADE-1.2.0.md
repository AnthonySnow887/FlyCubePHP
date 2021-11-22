UPGRADE FROM 1.1.2 to 1.2.0
===========================

Routes
------

 * Removed get(string $uri, string $controller, string $action), use get(string $uri, array $args = []).
 * Removed post(string $uri, string $controller, string $action), use post(string $uri, array $args = []).
 * Removed put(string $uri, string $controller, string $action), use put(string $uri, array $args = []).
 * Removed patch(string $uri, string $controller, string $action), use patch(string $uri, array $args = []).
 * Removed delete(string $uri, string $controller, string $action), use delete(string $uri, array $args = []).
 
 Before:
 ```
 get('/test', 'Test', 'show');
 ```
 
 After:
 ```
 get('/test', [ 'to' => 'Test#show' ]);
 
 or
 
 get('/test', [ 'controller' => 'Test', 'action' => 'show' ]);
 ```
 
Supported input arguments:
 - [string] to            - The name of the controller and the action separated '#' (Test#show)
 - [string] controller    - The name of the controller
 - [string] action        - The name of the controller action
 - [string] as            - Alias for quick access to the route (define is automatically generated)
 - ...
 - Other arguments will be transferred as input parameters.
 
Examples:
```
get('/test', [ 'to' => 'Test#show' ])

where:
  - Test  - The name of the controller class without expansion controller
  - show  - The name of the controller action
```
```
get('/test', [ 'controller' => 'Test', 'action' => 'show' ])

where:
  - Test  - The name of the controller class without expansion controller
  - show  - The name of the controller action
```
```
get('/test', [ 'to' => 'Test#show', 'as' => 'test' ])

where:
  - Test  - The name of the controller class without expansion controller
  - show  - The name of the controller action
  - test  - Alias for quick access to url (use define 'test_url')
```
