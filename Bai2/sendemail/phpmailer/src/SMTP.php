<?php

/**
 * PHPMailer RFC821 SMTP email transport class.
 * PHP Version 5.5.
 *
 * @see       https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 *
 * @author    Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author    Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author    Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author    Brent R. Matzelle (original founder)
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note      This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

namespace PHPMailer\PHPMailer;

/**
 * PHPMailer RFC821 SMTP email transport class.
 * Implements RFC 821 SMTP commands and provides some utility methods for sending mail to an SMTP server.
 *
 * @author Chris Ryan
 * @author Marcus Bointon <phpmailer@synchromedia.co.uk>
 */
class SMTP
{
    /**
     * The PHPMailer SMTP version number.
     *
     * @var string
     */
    const VERSION = '6.8.1';

    /**
     * SMTP line break constant.
     *
     * @var string
     */
    const LE = "\r\n";

    /**
     * The SMTP port to use if one is not specified.
     *
     * @var int
     */
    const DEFAULT_PORT = 25;

    /**
     * The SMTPs port to use if one is not specified.
     *
     * @var int
     */
    const DEFAULT_SECURE_PORT = 465;

    /**
     * The maximum line length allowed by RFC 5321 section 4.5.3.1.6,
     * *excluding* a trailing CRLF break.
     *
     * @see https://tools.ietf.org/html/rfc5321#section-4.5.3.1.6
     *
     * @var int
     */
    const MAX_LINE_LENGTH = 998;

    /**
     * The maximum line length allowed for replies in RFC 5321 section 4.5.3.1.5,
     * *including* a trailing CRLF line break.
     *
     * @see https://tools.ietf.org/html/rfc5321#section-4.5.3.1.5
     *
     * @var int
     */
    const MAX_REPLY_LENGTH = 512;

    /**
     * Debug level for no output.
     *
     * @var int
     */
    const DEBUG_OFF = 0;

    /**
     * Debug level to show client -> server messages.
     *
     * @var int
     */
    const DEBUG_CLIENT = 1;

    /**
     * Debug level to show client -> server and server -> client messages.
     *
     * @var int
     */
    const DEBUG_SERVER = 2;

    /**
     * Debug level to show connection status, client -> server and server -> client messages.
     *
     * @var int
     */
    const DEBUG_CONNECTION = 3;

    /**
     * Debug level to show all messages.
     *
     * @var int
     */
    const DEBUG_LOWLEVEL = 4;

    /**
     * Debug output level.
     * Options:
     * * self::DEBUG_OFF (`0`) No debug output, default
     * * self::DEBUG_CLIENT (`1`) Client commands
     * * self::DEBUG_SERVER (`2`) Client commands and server responses
     * * self::DEBUG_CONNECTION (`3`) As DEBUG_SERVER plus connection status
     * * self::DEBUG_LOWLEVEL (`4`) Low-level data output, all messages.
     *
     * @var int
     */
    public $do_debug = self::DEBUG_OFF;

    /**
     * How to handle debug output.
     * Options:
     * * `echo` Output plain-text as-is, appropriate for CLI
     * * `html` Output escaped, line breaks converted to `<br>`, appropriate for browser output
     * * `error_log` Output to error log as configured in php.ini
     * Alternatively, you can provide a callable expecting two params: a message string and the debug level:
     *
     * ```php
     * $smtp->Debugoutput = function($str, $level) {echo "debug level $level; message: $str";};
     * ```
     *
     * Alternatively, you can pass in an instance of a PSR-3 compatible logger, though only `debug`
     * level output is used:
     *
     * ```php
     * $mail->Debugoutput = new myPsr3Logger;
     * ```
     *
     * @var string|callable|\Psr\Log\LoggerInterface
     */
    public $Debugoutput = 'echo';

    /**
     * Whether to use VERP.
     *
     * @see http://en.wikipedia.org/wiki/Variable_envelope_return_path
     * @see http://www.postfix.org/VERP_README.html Info on VERP
     *
     * @var bool
     */
    public $do_verp = false;

    /**
     * The timeout value for connection, in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2.
     * This needs to be quite high to function correctly with hosts using greetdelay as an anti-spam measure.
     *
     * @see http://tools.ietf.org/html/rfc2821#section-4.5.3.2
     *
     * @var int
     */
    public $Timeout = 300;

    /**
     * How long to wait for commands to complete, in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2.
     *
     * @var int
     */
    public $Timelimit = 300;

    /**
     * Patterns to extract an SMTP transaction id from reply to a DATA command.
     * The first capture group in each regex will be used as the ID.
     * MS ESMTP returns the message ID, which may not be correct for internal tracking.
     *
     * @var string[]
     */
    protected $smtp_transaction_id_patterns = [
        'exim' => '/[\d]{3} OK id=(.*)/',
        'sendmail' => '/[\d]{3} 2.0.0 (.*) Message/',
        'postfix' => '/[\d]{3} 2.0.0 Ok: queued as (.*)/',
        'Microsoft_ESMTP' => '/[0-9]{3} 2.[\d].0 (.*)@(?:.*) Queued mail for delivery/',
        'Amazon_SES' => '/[\d]{3} Ok (.*)/',
        'SendGrid' => '/[\d]{3} Ok: queued as (.*)/',
        'CampaignMonitor' => '/[\d]{3} 2.0.0 OK:([a-zA-Z\d]{48})/',
        'Haraka' => '/[\d]{3} Message Queued \((.*)\)/',
        'ZoneMTA' => '/[\d]{3} Message queued as (.*)/',
        'Mailjet' => '/[\d]{3} OK queued as (.*)/',
    ];

    /**
     * The last transaction ID issued in response to a DATA command,
     * if one was detected.
     *
     * @var string|bool|null
     */
    protected $last_smtp_transaction_id;

    /**
     * The socket for the server connection.
     *
     * @var ?resource
     */
    protected $smtp_conn;

    /**
     * Error information, if any, for the last SMTP command.
     *
     * @var array
     */
    protected $error = [
        'error' => '',
        'detail' => '',
        'smtp_code' => '',
        'smtp_code_ex' => '',
    ];

    /**
     * The reply the server sent to us for HELO.
     * If null, no HELO string has yet been received.
     *
     * @var string|null
     */
    protected $helo_rply;

    /**
     * The set of SMTP extensions sent in reply to EHLO command.
     * Indexes of the array are extension names.
     * Value at index 'HELO' or 'EHLO' (according to command that was sent)
     * represents the server name. In case of HELO it is the only element of the array.
     * Other values can be boolean TRUE or an array containing extension options.
     * If null, no HELO/EHLO string has yet been received.
     *
     * @var array|null
     */
    protected $server_caps;

    /**
     * The most recent reply received from the server.
     *
     * @var string
     */
    protected $last_reply = '';

    /**
     * Output debugging info via a user-selected method.
     *
     * @param string $str   Debug string to output
     * @param int    $level The debug level of this message; see DEBUG_* constants
     *
     * @see SMTP::$Debugoutput
     * @see SMTP::$do_debug
     */
    protected function edebug($str, $level = 0)
    {
        if ($level > $this->do_debug) {
            return;
        }
        //Is this a PSR-3 logger?
        if ($this->Debugoutput instanceof \Psr\Log\LoggerInterface) {
            $this->Debugoutput->debug($str);

            return;
        }
        //Avoid clash with built-in function names
        if (is_callable($this->Debugoutput) && !in_array($this->Debugoutput, ['error_log', 'html', 'echo'])) {
            call_user_func($this->Debugoutput, $str, $level);

            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                //Don't output, just log
                error_log($str);
                break;
            case 'html':
                //Cleans up output a bit for a better looking, HTML-safe output
                echo gmdate('Y-m-d H:i:s'), ' ', htmlentities(
                    preg_replace('/[\r\n]+/', '', $str),
                    ENT_QUOTES,
                    'UTF-8'
                ), "<br>\n";
                break;
            case 'echo':
            default:
                //Normalize line breaks
                $str = preg_replace('/\r\n|\r/m', "\n", $str);
                echo gmdate('Y-m-d H:i:s'),
                "\t",
                    //Trim trailing space
                trim(
                    //Indent for readability, except for trailing break
                    str_replace(
                        "\n",
                        "\n                   \t                  ",
                        trim($str)
                    )
                ),
                "\n";
        }
    }

    /**
     * Connect to an SMTP server.
     *
     * @param string $host    SMTP server IP or host name
     * @param int    $port    The port number to connect to
     * @param int    $timeout How long to wait for the connection to open
     * @param array  $options An array of options for stream_context_create()
     *
     * @return bool
     */
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        //Clear errors to avoid confusion
        $this->setError('');
        //Make sure we are __not__ connected
        if ($this->connected()) {
            //Already connected, generate error
            $this->setError('Already connected to a server');

            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }
        //Connect to the SMTP server
        $this->edebug(
            "Connection: opening to $host:$port, timeout=$timeout, options=" .
            (count($options) > 0 ? var_export($options, true) : 'array()'),
            self::DEBUG_CONNECTION
        );

        $this->smtp_conn = $this->getSMTPConnection($host, $port, $timeout, $options);

        if ($this->smtp_conn === false) {
            //Error info already set inside `getSMTPConnection()`
            return false;
        }

        $this->edebug('Connection: opened', self::DEBUG_CONNECTION);

        //Get any announcement
        $this->last_reply = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $this->last_reply, self::DEBUG_SERVER);
        $responseCode = (int)substr($this->last_reply, 0, 3);
        if ($responseCode === 220) {
            return true;
        }
        //Anything other than a 220 response means something went wrong
        //RFC 5321 says the server will wait for us to send a QUIT in response to a 554 error
        //https://tools.ietf.org/html/rfc5321#section-3.1
        if ($responseCode === 554) {
            $this->quit();
        }
        //This will handle 421 responses which may not wait for a QUIT (e.g. if the server is being shut down)
        $this->edebug('Connection: closing due to error', self::DEBUG_CONNECTION);
        $this->close();
        return false;
    }

    /**
     * Create connection to the SMTP server.
     *
     * @param string $host    SMTP server IP or host name
     * @param int    $port    The port number to connect to
     * @param int    $timeout How long to wait for the connection to open
     * @param array  $options An array of options for stream_context_create()
     *
     * @return false|resource
     */
    protected function getSMTPConnection($host, $port = null, $timeout = 30, $options = [])
    {
        static $streamok;
        //This is enabled by default since 5.0.0 but some providers disable it
        //Check this once and cache the result
        if (null === $streamok) {
            $streamok = function_exists('stream_socket_client');
        }

        $errno = 0;
        $errstr = '';
        if ($streamok) {
            $socket_context = stream_context_create($options);
            set_error_handler([$this, 'errorHandler']);
            $connection = stream_socket_client(
                $host . ':' . $port,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $socket_context
            );
        } else {
            //Fall back to fsockopen which should work in more places, but is missing some features
            $this->edebug(
                'Connection: stream_socket_client not available, falling back to fsockopen',
                self::DEBUG_CONNECTION
            );
            set_error_handler([$this, 'errorHandler']);
            $connection = fsockopen(
                $host,
                $port,
                $errno,
                $errstr,
                $timeout
            );
        }
        restore_error_handler();

        //Verify we connected properly
        if (!is_resource($connection)) {
            $this->setError(
                'Failed to connect to server',
                '',
                (string) $errno,
                $errstr
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error']
                . ": $errstr ($errno)",
                self::DEBUG_CLIENT
            );

            return false;
        }

        //SMTP server can take longer to respond, give longer timeout for first read
        //Windows does not have support for this timeout function
        if (strpos(PHP_OS, 'WIN') !== 0) {
            $max = (int)ini_get('max_execution_time');
            //Don't bother if unlimited, or if set_time_limit is disabled
            if (0 !== $max && $timeout > $max && strpos(ini_get('disable_functions'), 'set_time_limit') === false) {
                @set_time_limit($timeout);
            }
            stream_set_timeout($connection, $timeout, 0);
        }

        return $connection;
    }

    /**
     * Initiate a TLS (encrypted) session.
     *
     * @return bool
     */
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }

        //Allow the best TLS version(s) we can
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

        //PHP 5.6.7 dropped inclusion of TLS 1.1 and 1.2 in STREAM_CRYPTO_METHOD_TLS_CLIENT
        //so add them back in manually if we can
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }

        //Begin encrypted connection
        set_error_handler([$this, 'errorHandler']);
        $crypto_ok = stream_socket_enable_crypto(
            $this->smtp_conn,
            true,
            $crypto_method
        );
        restore_error_handler();

        return (bool) $crypto_ok;
    }

    /**
     * Perform SMTP authentication.
     * Must be run after hello().
     *
     * @see    hello()
     *
     * @param string $username The user name
     * @param string $password The password
     * @param string $authtype The auth type (CRAM-MD5, PLAIN, LOGIN, XOAUTH2)
     * @param OAuthTokenProvider $OAuth An optional OAuthTokenProvider instance for XOAUTH2 authentication
     *
     * @return bool True if successfully authenticated
     */
    public function authenticate(
        $username,
        $password,
        $authtype = null,
        $OAuth = null
    ) {
        if (!$this->server_caps) {
            $this->setError('Authentication is not allowed before HELO/EHLO');

            return false;
        }

        if (array_key_exists('EHLO', $this->server_caps)) {
            //SMTP extensions are available; try to find a proper authentication method
            if (!array_key_exists('AUTH', $this->server_caps)) {
                $this->setError('Authentication is not allowed at this stage');
                //'at this stage' means that auth may be allowed after the stage changes
                //e.g. after STARTTLS

                return false;
            }

            $this->edebug('Auth method requested: ' . ($authtype ?: 'UNSPECIFIED'), self::DEBUG_LOWLEVEL);
           