<?php

namespace FlyCubePHP\Core\Routes;

class RouteRedirect
{
    private $_uri;
    private $_status;

    function __construct(string $uri, int $status = 303) {
        $this->_uri = $uri;
        $this->_status = $status;
    }

    /**
     * URL маршрута перенаправления
     * @return string
     */
    public function uri(): string {
        return $this->_uri;
    }

    /**
     * Код HTTP
     * @return int
     */
    public function status(): int {
        return $this->_status;
    }

    /**
     * Есть ли у маршрута входные аргументы?
     * @return bool
     */
    public function hasUriArgs(): bool {
        return preg_match('/\%\{([a-zA-Z0-9_]*)\}/i', $this->uri());
    }
}