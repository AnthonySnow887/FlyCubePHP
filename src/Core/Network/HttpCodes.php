<?php
/**
 * Created by PhpStorm.
 * User => anton
 * Date => 31.08.21
 * Time => 16 =>29
 */

namespace FlyCubePHP\Core\Network;


class HttpCodes
{
    private $_codes = array();

    function __construct() {
        $this->_codes = [
            100 => [ 'title' => '100 Continue', 'code_title' => 'Продолжай', 'code_text' => '' ],
            101 => [ 'title' => '101 Switching Protocols', 'code_title' => 'Переключение протоколов', 'code_text' => '' ],
            102 => [ 'title' => '102 Processing', 'code_title' => 'Идёт обработка', 'code_text' => '' ],
            200 => [ 'title' => '200 OK', 'code_title' => 'Успех', 'code_text' => '' ],
            201 => [ 'title' => '201 Created', 'code_title' => 'Создано', 'code_text' => '' ],
            202 => [ 'title' => '202 Accepted', 'code_title' => 'Принято', 'code_text' => '' ],
            203 => [ 'title' => '203 Non-Authoritative Information', 'code_title' => 'Информация не авторитетна', 'code_text' => '' ],
            204 => [ 'title' => '204 No Content', 'code_title' => 'Нет содержимого', 'code_text' => '' ],
            205 => [ 'title' => '205 Reset Content', 'code_title' => 'Сбросить содержимое', 'code_text' => '' ],
            206 => [ 'title' => '206 Partial Content', 'code_title' => 'Частичное содержимое', 'code_text' => '' ],
            207 => [ 'title' => '207 Multi-Status', 'code_title' => 'Многостатусный', 'code_text' => '' ],
            208 => [ 'title' => '208 Already Reported', 'code_title' => 'Уже сообщалось', 'code_text' => '' ],
            226 => [ 'title' => '226 IM Used', 'code_title' => 'Использовано IM', 'code_text' => '' ],
            300 => [ 'title' => '300 Multiple Choices', 'code_title' => 'Множество выборов', 'code_text' => '' ],
            301 => [ 'title' => '301 Moved Permanently', 'code_title' => 'Перемещено навсегда', 'code_text' => '' ],
            302 => [ 'title' => '302 Moved Temporarily', 'code_title' => 'Перемещено временно', 'code_text' => '' ],
            303 => [ 'title' => '303 See Other', 'code_title' => 'Смотреть другое', 'code_text' => '' ],
            304 => [ 'title' => '304 Not Modified', 'code_title' => 'Не изменялось', 'code_text' => '' ],
            305 => [ 'title' => '305 Use Proxy', 'code_title' => 'Использовать прокси', 'code_text' => '' ],
            306 => [ 'title' => '306 -- Reserved --', 'code_title' => '', 'code_text' => '' ], // код использовался только в ранних спецификациях
            307 => [ 'title' => '307 Temporary Redirect', 'code_title' => 'Временное перенаправление', 'code_text' => '' ],
            308 => [ 'title' => '308 Permanent Redirect', 'code_title' => 'Постоянное перенаправление', 'code_text' => '' ],
            400 => [ 'title' => '400 Bad Request', 'code_title' => 'Неверный запрос', 'code_text' => 'Запрос не может быть понят сервером из-за некорректного синтаксиса.' ],
            401 => [ 'title' => '401 Unauthorized', 'code_title' => 'Неавторизованный запрос', 'code_text' => 'Для доступа к документу необходимо вводить пароль или быть зарегистрированным пользователем.' ],
            402 => [ 'title' => '402 Payment Required', 'code_title' => 'Необходима оплата за запрос', 'code_text' => 'Внутренняя ошибка или ошибка конфигурации сервера.' ],
            403 => [ 'title' => '403 Forbidden', 'code_title' => 'Доступ к ресурсу запрещен', 'code_text' => 'Доступ к документу запрещен.' ],
            404 => [ 'title' => '404 Not Found', 'code_title' => 'Ресурс не найден', 'code_text' => 'Запрашиваемый документ не существует.' ],
            405 => [ 'title' => '405 Method Not Allowed', 'code_title' => 'Недопустимый метод', 'code_text' => 'Метод, определенный в строке запроса (Request-Line), не дозволено применять для указанного ресурса.' ],
            406 => [ 'title' => '406 Not Acceptable', 'code_title' => 'Неприемлемый запрос', 'code_text' => 'Нужный документ существует, но не в том формате.' ],
            407 => [ 'title' => '407 Proxy Authentication Required', 'code_title' => 'Требуется идентификация прокси, файервола', 'code_text' => 'Необходима регистрация на прокси-сервере.' ],
            408 => [ 'title' => '408 Request Timeout', 'code_title' => 'Время запроса истекло', 'code_text' => 'Сайт не передал полный запрос в течение установленного времени и соединение было разорвано.' ],
            409 => [ 'title' => '409 Conflict', 'code_title' => 'Конфликт', 'code_text' => 'Запрос конфликтует с другим запросом или с конфигурацией сервера.' ],
            410 => [ 'title' => '410 Gone', 'code_title' => 'Ресурс недоступен', 'code_text' => 'Затребованный ресурс был окончательно удален с сайта.' ],
            411 => [ 'title' => '411 Length Required', 'code_title' => 'Необходимо указать длину', 'code_text' => 'Сервер отказывается принимать запрос без определенного заголовка Content-Length.' ],
            412 => [ 'title' => '412 Precondition Failed', 'code_title' => 'Сбой при обработке предварительного условия', 'code_text' => 'При проверке на сервере одного или более полей заголовка запроса обнаружено несоответствие (сбой или ошибка при обработке предварительного условия).' ],
            413 => [ 'title' => '413 Request Entity Too Large', 'code_title' => 'Тело запроса превышает допустимый размер', 'code_text' => 'Сервер отказывается обрабатывать запрос потому, что размер запроса больше того, что может обработать сервер.' ],
            414 => [ 'title' => '414 Request-URI Too Large', 'code_title' => 'Недопустимая длина URI запроса', 'code_text' => 'Сервер отказывается обслуживать запрос, потому что запрашиваемый URI (Request-URI) длиннее, чем сервер может интерпретировать.' ],
            415 => [ 'title' => '415 Unsupported Media Type', 'code_title' => 'Неподдерживаемый MIME тип', 'code_text' => 'Сервер отказывается обрабатывать запрос, потому что тело запроса имеет неподдерживаемый формат.' ],
            416 => [ 'title' => '416 Requested Range Not Satisfiable', 'code_title' => 'Диапазон не может быть обработан', 'code_text' => 'Сервер отказывается обрабатывать запрос, потому что значение поля Range в заголовке запроса указывает на недопустимый диапазон байтов.' ],
            417 => [ 'title' => '417 Expectation Failed', 'code_title' => 'Сбой при ожидании', 'code_text' => 'Сервер отказывается обрабатывать запрос, потому что значение поля Expect в заголовке запроса не соответствует ожиданиям.' ],
            422 => [ 'title' => '422 Unprocessable Entity', 'code_title' => 'Необрабатываемый элемент', 'code_text' => 'Сервер не в состоянии обработать один (или более) элемент запроса.' ],
            423 => [ 'title' => '423 Locked', 'code_title' => 'Заблокировано', 'code_text' => 'Сервер отказывается обработать запрос, так как один из требуемых ресурсов заблокирован.' ],
            424 => [ 'title' => '424 Failed Dependency', 'code_title' => 'Неверная зависимость', 'code_text' => 'Сервер отказывается обработать запрос, так как один из зависимых ресурсов заблокирован.' ],
            426 => [ 'title' => '426 Upgrade Required', 'code_title' => 'Требуется обновление', 'code_text' => 'Сервер запросил апгрейд соединения до SSL, но SSL не поддерживается клиентом.' ],
            428 => [ 'title' => '428 Precondition Required', 'code_title' => 'Необходимо предусловие', 'code_text' => '' ],
            429 => [ 'title' => '429 Too Many Requests', 'code_title' => 'Слишком много запросов', 'code_text' => '' ],
            431 => [ 'title' => '431 Request Header Fields Too Large', 'code_title' => 'Поля заголовка запроса слишком большие', 'code_text' => '' ],
            449 => [ 'title' => '449 Retry With', 'code_title' => 'Повторить с', 'code_text' => '' ],
            451 => [ 'title' => '451 Unavailable For Legal Reasons', 'code_title' => 'Недоступно по юридическим причинам', 'code_text' => '' ],
            499 => [ 'title' => '499 Client Closed Request', 'code_title' => 'Клиент закрыл соединение', 'code_text' => '' ],
            500 => [ 'title' => '500 Internal Server Error', 'code_title' => 'Внутренняя ошибка сервера', 'code_text' => 'Сервер столкнулся с непредвиденным условием, которое не позволяет ему выполнить запрос.' ],
            501 => [ 'title' => '501 Not Implemented', 'code_title' => 'Метод не поддерживается', 'code_text' => 'Сервер не поддерживает функциональные возможности, требуемые для выполнения запроса.' ],
            502 => [ 'title' => '502 Bad Gateway', 'code_title' => 'Ошибка шлюза', 'code_text' => 'Сервер, действуя в качестве шлюза или прокси-сервера, получил недопустимый ответ от следующего сервера в цепочке запросов, к которому обратился при попытке выполнить запрос.' ],
            503 => [ 'title' => '503 Service Unavailable', 'code_title' => 'Служба недоступна', 'code_text' => 'Возникла ошибка из-за временной перегрузки или отключения на техническое обслуживание сервера.' ],
            504 => [ 'title' => '504 Gateway Timeout', 'code_title' => 'Время прохождения через межсетевой шлюз истекло', 'code_text' => 'Сервер, при работе в качестве внешнего шлюза или прокси-сервера, своевременно не получил отклик от вышестоящего сервера, к которому он обратился, пытаясь выполнить запрос.' ],
            505 => [ 'title' => '505 HTTP Version Not Supported', 'code_title' => 'Версия НТТР не поддерживается', 'code_text' => 'Сервер не поддерживает или отказывается поддерживать версию HTTP-протокола, которая используется в сообщении запроса робота.' ],
            507 => [ 'title' => '507 Insufficient Storage', 'code_title' => 'Недостаточно места', 'code_text' => 'Сервер не может обработать запрос из-за недостатка места на диске.' ],
            510 => [ 'title' => '510 Not Extended', 'code_title' => 'Отсутствуют расширения', 'code_text' => 'Сервер не может обработать запрос из-за того, что запрашиваемое расширение не поддерживается.' ],
            511 => [ 'title' => '511 Network Authentication Required', 'code_title' => 'Требуется сетевая аутентификация', 'code_text' => '' ],
            520 => [ 'title' => '520 Unknown Error', 'code_title' => 'Неизвестная ошибка', 'code_text' => '' ],
            521 => [ 'title' => '521 Web Server Is Down', 'code_title' => 'Веб-сервер не работает', 'code_text' => '' ],
            522 => [ 'title' => '522 Connection Timed Out', 'code_title' => 'Соединение не отвечает', 'code_text' => '' ],
            523 => [ 'title' => '523 Origin Is Unreachable', 'code_title' => 'Источник недоступен', 'code_text' => '' ],
            524 => [ 'title' => '524 A Timeout Occurred', 'code_title' => 'Время ожидания истекло', 'code_text' => '' ],
            525 => [ 'title' => '525 SSL Handshake Failed', 'code_title' => 'Квитирование SSL не удалось', 'code_text' => '' ],
            526 => [ 'title' => '526 Invalid SSL Certificate', 'code_title' => 'Недействительный сертификат SSL', 'code_text' => '' ]
        ];
    }

    /**
     * Получить массив с информацией по HTTP коду
     * @param int $code - HTTP код (100 <= CODE >= 526)
     * @return array
     */
    public function codeInfo(int $code): array {
        if (!isset($this->_codes[$code]))
            return [];
        return $this->_codes[$code];
    }

    /**
     * Получить заголовок HTTP кода
     * @param int $code
     * @return string
     */
    static public function title(int $code): string {
        $c = new HttpCodes();
        $info = $c->codeInfo($code);
        unset($c);
        if (isset($info['title']))
            return $info['title'];
        return "";
    }

    /**
     * Получить короткое описание HTTP кода
     * @param int $code
     * @return string
     */
    static public function codeTitle(int $code): string {
        $c = new HttpCodes();
        $info = $c->codeInfo($code);
        unset($c);
        if (isset($info['code_title']))
            return $info['code_title'];
        return "";
    }

    /**
     * Получить полное описание HTTP кода
     * @param int $code
     * @return string
     */
    static public function codeText(int $code): string {
        $c = new HttpCodes();
        $info = $c->codeInfo($code);
        unset($c);
        if (isset($info['code_text']))
            return $info['code_text'];
        return "";
    }
}