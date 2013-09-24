<?php
/**
 * @author      OLWerdffelynir <werdffelynir@gmail.com>
 * @copyright   Copyright (C), 2013
 * @license     GNU General Public License 3 (http://www.gnu.org/licenses/)
 *              Refer to the LICENSE file distributed within the package.
 *
 * @link
 *
 * @internal    Inspired by OLWerdffelynir @ https://github.com/
 */

class Ractor
{
    /**
     * @var string URL только хоста
     */
    public $urlHost;

    /**
     * @var string URL полный до корня приложения
     */
    public $url;

    /**
     * @var string Путь полный до корня приложения
     */
    public $pathRoot;
    /**
     * @var array Массив с списком URIов для сравнения
     */
    private $listUri = array();

    /**
     * @var array Массив с списком URIов для вызова
     */
    private $listCall = array();

    /**
     * @var string Регулярное выращение для чистки запросов
     */
    private $liTrim = '/\^$';

    /**
     * @var string По умолчанию если $page404 == null выводиться ето сообщение.
     */
    private $page404massage = "
        <div style='display:block; margin-top:100px; font-family: Lucida Console, Verdana; font-size: 200%;'>
        <h1 style='color:#6B0000; text-align: center;'>ERROR 404</h1>
        <h3 style='color:#1C0038; text-align: center;'>PAGE NOT FOUND</h3></div>
    ";

    /**
     * @var string Может назначаться страница ошибки
     */
    private $page404 = null;

    /**
     * @var string Ключ для сессии flash
     */
    private $flashKey = null;

    /**
     * Конструктор обрабатывает параметры и сразу определяет для приложение
     * его URL адреса и Пути, абсолютные и отностительные. Принимает массив
     * параметров, заданых при инициализации класса
     *
     * @param string $uri A path such as about/system
     */
    public function __construct(array $param = null)
    {
        if (isset($param['domin']))
            $this->path($param['domin']);
        else
            $this->path();

        if (isset($param['path']))
            $this->pathRoot = $param['path'];
        else
            $this->pathRoot = __DIR__;

        if (isset($param['page404']))
            $this->page404 = $param['page404'];

        if (isset($param['page404massage']))
            $this->page404massage = $param['page404massage'];

        if (isset($param['flashKey']))
            $this->flashKey = $param['flashKey'];
        else
            $this->flashKey = '_flash';

    }

    /**
     * Создание роутера. Добавляет URI и функции в списки для обработки
     *
     * @param string $uri Путь для поиска
     * @param object $function Функция или анонимная или имя существующей
     */
    public function re($uri, $function)
    {
        $uri = trim($uri, $this->liTrim);
        $this->listUri[] = $uri;
        $this->listCall[] = $function;
    }

    public static $instance;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     *
     * Сравнивает имеющие в списке роуты и выполняет заданую функцию
     * Параметров нет, ничего не возвращает.
     *
     */
    public function run()
    {
        $uri = isset($_REQUEST['uri']) ? $_REQUEST['uri'] : '/';
        $uri = trim($uri, $this->liTrim);

        $replacementValues = array();

        /**
         * Пропускает список через URL
         */
        foreach ($this->listUri as $listKey => $listUri) {
            /**
             * Поиск совпадения
             */
            if (preg_match("#^$listUri$#", $uri)) {
                $realUri = explode('/', $uri);
                $fakeUri = explode('/', $listUri);

                /**
                 * Соберает значение "::" для замены с реальным URLом
                 */
                foreach ($fakeUri as $key => $value) {
                    if ($value == '.+') {
                        $replacementValues[] = $realUri[$key];
                    }
                }

                /**
                 * Создает метод регистратор собитя до роутинга
                 */
                ($before = $this->before()) && $this->apply($before, array());

                /**
                 * Вызывает обработку функции и передает массив аргументов в нее
                 */
                call_user_func_array($this->listCall[$listKey], $replacementValues);

                /**
                 * Создает метод регистратор собитя после роутинга
                 */
                ($after = $this->after()) && $this->apply($after, array());
            }

        }

        /**
         * Определение что роутера не существует и обработка на вывод page404massage или заданоего и существующего page404 роута
         */
        if ($realUri == null && $fakeUri == null) {
            if ($this->page404 == null) {
                echo $this->page404massage;
            } else {
                $this->redirect('/'.$this->page404);
            }

        }
    }


    /**
     * Обработчик Ссылок. Определяет домен (если не указа в необезательном параметре)
     * и если имеються под деректории приложения. Назначает свойства класс.
     * Возвращает относительные и абсолютные пути.
     *
     * @use urlHost назначеает доменное имя URL ссылкой
     * @use url     назначеает полный путь с доменным иминем и возможными под каталогами URL ссылкой
     *
     * @param string $domin Необезательные параметр домен.
     *
     */
    public function path($domin = null)
    {
        if ($domin == null) {
            $httpHost = $_SERVER['HTTP_HOST'];
        } else {
            $httpHost = $domin;
        }

        $this->urlHost = "http://" . $httpHost;

        $scr_nam_arr = explode("/", trim($_SERVER['SCRIPT_NAME']));

        array_pop($scr_nam_arr);

        $scr_nam_arr = array_filter($scr_nam_arr, function ($el) {
            return !empty($el);
        });

        $pathfolder = join('/', $scr_nam_arr);

        $this->url = "http://" . $httpHost . "/" . $pathfolder;

    }

    /**
     * Метод определяющий текущую страницу
     *
     * @return string $url Возвращает строку ссылку
     */
    public function thisUrl()
    {
        $url = @($_SERVER["HTTPS"] != 'on') ? 'http://' . $_SERVER["SERVER_NAME"] : 'https://' . $_SERVER["SERVER_NAME"];
        $url .= ($_SERVER["SERVER_PORT"] !== 80) ? ":" . $_SERVER["SERVER_PORT"] : "";
        $url .= $_SERVER["REQUEST_URI"];
        return $url;
    }

    /**
     * Перенаправление по указаному роуту, относитльный URL. Метод настраивает ответ с кодом HTTP и время задержки.
     * Если время задержки задано, эта функция всегда будет возвращать логическое TRUE,
     * Если заголовок уже прошел код всеравно будет остановлен halt()
     *
     * @param string $url   Редирек URL
     * @param int $code  HTTP код; по умолчанию 302
     * @param int $delay Редирек с задержкой с секунднах
     *
     * @return bool Boolean true if time delay is given
     */
    public function redirect($url = null, $code = 302, $delay = 0)
    {
        $url = isset($url) ? $this->url . $url : $this->thisUrl;

        if ($delay) {
            header('Refresh: ' . $delay . '; url=' . $url, true);
        } else {
            header('Location: ' . $url, true, $code);
            $this->halt();
        }
        return true;
    }


    /**
     *
     * @var array Хранит зарегистрированые HTML коды заголовков, для обработки его функцией обратного вызова
     *
     */
    public $callbacks = array();


    /**
     * Возвращает или задает обработчик ошибок для конкретного кода. Функция обратного
     * вызова может принять сообщение параметром, которое может быть что угодно от простых
     * строк до сложных.
     *
     * @param mixed $code     Код ошибки HTTP 4xx, 5xx или строка - назначеный код ошибки.
     * @param callable $callback Функция обратного вызова
     *
     * @return mixed
     */
    function error($code = null, $callback = null)
    {
        if (func_num_args() > 1) {
            $this->callbacks[$code] = $callback;
        } elseif (func_num_args()) {
            return isset($this->callbacks[$code]) ? $this->callbacks[$code] : null;
        } else {
            return $this->callbacks;
        }
    }


    /**
     * Прерывает текущей запрос и посылает код заголовка HTTP, вызывает назначеный
     * обработчик error('code','foo'), и выходит.
     *
     * @param mixed $code    Код ошибки HTTP 4xx, 5xx или строка - назначеный код ошибки.
     * @param mixed $message Сообщение, будет передано обработчику в error() как аргумент.
     *
     */
    public function halt($code = null, $message = null)
    {
        $status = $this->http_status($code);

        if (isset($status)) {
            header("HTTP/1.1 $code $status", true, $code);
        }

        if (($callback = $this->error($code)) !== null) {
            $this->call($callback, $message);
        }

        exit;
    }


    /**
     * Возвращает статус HTTP заголовка поп его коду, указаного в внутренем массиве
     *
     * @param int $code HTTP код статуса
     *
     * @return string|null
     */
    function http_status($code = null)
    {
        $codes = array(
            100 => 'Continue', 'Switching Protocols',
            200 => 'OK', 'Created', 'Accepted', 'Non-Authoritative Information', 'No Content', 'Reset Content', 'Partial Content',
            300 => 'Multiple Choices', 'Moved Permanently', 'Moved Temporarily', 'See Other', 'Not Modified', 'Use Proxy',
            400 => 'Bad Request', 'Unauthorized', 'Payment Required', 'Forbidden', 'Not Found', 'Method Not Allowed', 'Not Acceptable', 'Proxy Authentication Required', 'Request Time-out', 'Conflict', 'Gone', 'Length Required', 'Precondition Failed', 'Request Entity Too Large', 'Request-URI Too Large', 'Unsupported Media Type',
            500 => 'Internal Server Error', 'Not Implemented', 'Bad Gateway', 'Service Unavailable', 'Gateway Time-out', 'HTTP Version not supported',
        );

        return isset($codes[$code]) ? $codes[$code] : null;
    }


    /**
     * Выполнение функции с аргуметами массив. При ошибке обрабатывает исключение.
     *
     * @param callable $callable Функция для запуска
     * @param array $args     Аргумент функции массив
     *
     * @throws
     *
     * @return mixed возвращает с результат с функции callable
     */
    function apply($callable, $args = array())
    {
        if (!is_callable($callable)) {
            throw new \RuntimeException('invalid callable');
        }
        return call_user_func_array($callable, $args);
    }


    /**
     * Вызов функции с аргументами
     *
     * @param callable $callable Функция для запуска
     * @param mixed $arg      Аргумент функции
     * @param mixed $arg,...  Неограниченное необязательные аргументы для обратного вызова
     *
     * @return mixed возвращает с результат с функции callable
     */
    function call($callable, $arg = null)
    {
        $args = func_get_args();
        $callable = array_shift($args);
        return $this->apply($callable, $args);
    }


    /**
     *
     * @var array Хранит зарегистрированые имена обработчиков, хуки короч.
     *
     */
    public $handlers = array();

    /**
     * Возвращает или задает обработчиков событий. Два аргумента имя события должно быть уже зарегестирировано методом exec_action()
     * и функция для обработки, которая вызываеться в месте устанвки метода exec_action() с одноименным аргументом.
     * Возвращает NULL для незарегистированых имен.
     *
     * @param string $event    Event name
     * @param callable $callback Event handler callback
     *
     * @return mixed
     */
    public function add_action($event = null, $callback = null)
    {

        if (func_num_args() > 1) {
            $this->handlers[$event][] = $callback;
        } elseif (func_num_args()) {
            return isset($this->handlers[$event]) ? $this->handlers[$event] : null;
        } else {
            return $this->handlers;
        }


    }

    /**
     * Назначения и установка позиции выполнения обработчиков событий.
     *
     * @param string $event Имя обработчика события
     *
     * @return mixed
     */
    public function exec_action($event)
    {
        $data = func_get_args();
        array_shift($data);

        if ($handlers = $this->add_action($event)) {

            foreach ($handlers as $callback) {
                $this->apply($callback, $data);
            }

        }
    }

    /**
     * Регистрация функции обратного вызова 'before'. Событие происходит до роутинга
     *
     * @param callable $callback 'before' принимает обработчик события функцию по имени своего аргумента
     *
     * @return mixed
     */
    function before($callback = null)
    {
        static $before;

        if (func_num_args()) {
            $before = $callback;
        } else {
            return $before;
        }
    }


    /**
     * Регистрация функции обратного вызова 'after'. Событие происходит после роутинга
     *
     * @param callable $callback 'after' принимает обработчик события функцию по имени своего аргумента
     *
     * @return mixed
     */
    function after($callback = null)
    {
        static $after;

        if (func_num_args()) {
            $after = $callback;
        } else {
            return $after;
        }
    }


    /**
     * Возвращает или задает flash для выбраного запроса, следующего запроса.
     * Передайте два аргументав качестве ключ и значение для установки flash.
     * Передать один аргумент для приема (возврата) значения после роутинга.
     *
     * @param string $key   Flash key
     * @param mixed $value Flash value
     * @param bool $keep  Keep the flash for the next request; defaults to true
     *
     * @return mixed
     */
    public function flash($key = null, $value = null, $keep = true)
    {
        $storage = array();

        if (!isset($_SESSION)) session_start();

        $flash = $this->flashKey;

        if (func_num_args() > 1) {

            $old = isset($_SESSION[$flash][$key]) ? $_SESSION[$flash][$key] : null;

            if (isset($value)) {

                $_SESSION[$flash][$key] = $value;

                if ($keep) {
                    $storage[$key] = $value;
                } else {
                    unset($storage[$key]);
                }

            } else {
                unset($storage[$key]);
                unset($_SESSION[$flash][$key]);
            }

            return $old;

        } elseif (func_num_args()) {

            return isset($_SESSION[$flash][$key]) ? $_SESSION[$flash][$key] : null;

        } else {

            return $storage;

        }


    }


} // END CLASS Route


