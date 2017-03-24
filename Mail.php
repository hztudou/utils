<?php

/**
 * 邮件发送类
 * 需要的php扩展:sockets和fileinfo
 * @author tangqin<tangqin23@gmail.com>
 * @created 17/2/20 21:29
 * @example
 * $mail = new Mail();
 * $mail->setServer("XXXXX", "XXXXX@XXXXX", "XXXXX"); 设置smtp服务器
 * $mail->setFrom("XXXXX"); 设置发件人
 * $mail->setReceiver("XXXXX"); 设置收件人
 * $mail->setReceiver(["XXXXX","XXXXX"]); 设置收件人,支持多个收件人
 * $mail->setCc(["XXXXX","XXXXX"]); 设置抄送
 * $mail->setBcc(["XXXXX","XXXXX"]); 设置密送
 * $mail->setMailInfo("test", "test"); 设置邮件主题、内容
 * $mail->sendMail(); 发送
 */
class Mail
{
    /**
     * @var string 用户名
     */
    protected $username;
    /**
     * @var string 密码
     */
    protected $password;
    /**
     * @var string 邮件服务器
     */
    protected $server;
    /**
     * @var int 服务器端口
     */
    protected $port = 25;
    /**
     * @var string 发件人
     */
    protected $from;
    /**
     * @var array 收件人
     */
    protected $to = [];
    /**
     * @var array 抄送
     */
    protected $cc = [];
    /**
     * @var array 密送
     */
    protected $bcc = [];
    /**
     * @var string 主题
     */
    protected $subject;
    /**
     * @var array 邮件头部
     */
    protected $headers = [];
    /**
     * @var string 邮件正文
     */
    protected $body;
    /**
     * @var boolean 是否为html
     */
    protected $isHtml = false;
    /**
     * @var reource socket资源
     */
    protected $socket;
    /**
     * @var string 错误信息
     */
    protected $errorMessage;

    /**
     * 设置邮件传输服务器
     * @param $server 服务器的IP或者域名
     * @param string $username 账号
     * @param string $password 密码
     * @param int $port 端口，smtp默认端口25
     * @return bool
     */
    public function setServer($server, $username = '', $password = '', $port = 25)
    {
        $this->server = $server;
        $this->port = $port;
        if (!empty($username)) {
            $this->username = $username;
        }
        if (!empty($password)) {
            $this->password = $password;
        }
        return $this;
    }

    /**
     * 设置发件人
     * @param string $from 发件人地址
     * @return bool
     */
    public function setFrom($from)
    {
        $this->from = $from;
        return $this;
    }

    /**
     * 设置收件人
     * @param string|array $to 收件人地址
     * @return bool
     */
    public function setReceiver($to)
    {
        if(is_array($to)) {
            foreach ($to as $val) {
                $this->to[] = $val;
            }
        } else {
            $this->to[] = $to;
        }
        return $this;
    }

    /**
     * 设置抄送
     * @param $cc
     * @return $this
     */
    public function setCc($cc)
    {
        if(is_array($cc)) {
            foreach ($cc as $val) {
                $this->cc[] = $val;
            }
        } else {
            $this->cc[] = $cc;
        }
        return $this;
    }

    /**
     * 设置密送
     * @param $bcc
     * @return $this
     */
    public function setBcc($bcc)
    {
        if(is_array($bcc)) {
            foreach ($bcc as $val) {
                $this->bcc[] = $val;
            }
        } else {
            $this->bcc[] = $bcc;
        }
        return $this;
    }

    /**
     * 设置邮件信息
     * @param $subject 邮件主题
     * @param $body 邮件内容
     * @return bool
     */
    public function setMailInfo($subject, $body, $isHtml = false)
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->isHtml = $isHtml;
        return $this;
    }

    /**
     * 发送邮件
     * @return bool
     */
    public function sendMail()
    {
        $this->buildHeaders();
        $command = $this->getCommand();
        $this->socket();
        foreach ($command as $value) {
            if (!$this->sendCommand($value[0], $value[1])) {
                return false;
            }
        }
        $this->close();
        return true;
    }

    /**
     * 返回错误信息
     * @return string
     */
    public function error()
    {
        if (!isset($this->errorMessage)) {
            $this->errorMessage = '';
        }
        return $this->errorMessage;
    }

    /**
     * 设置头部信息
     * @param $header
     * @param $value
     */
    protected function setHeader($header, $value)
    {
        $this->headers[$header] = $value;
    }

    protected function buildHeaders()
    {
        $this->setHeader('Mime-Version', '1.0');
        if($this->isHtml) {
            $this->setHeader('Content-Type', 'text/html; charset="utf-8"');
        } else {
            $this->setHeader('Content-Type', 'text/plain; charset="utf-8"');
        }
        $this->setHeader('Content-Transfer-Encoding', '7bit');
    }
    /**
     * 返回mail命令
     * @return array
     */
    protected function getCommand()
    {
        $mail = '';
        foreach ($this->headers as $key => $val) {
            $mail .= "$key:$val\r\n";
        }
        $mail .= "FROM:{$this->from}\r\n";

        if(!empty($this->to)) {
            $mail .= "TO:" . implode(", ", $this->to)."\r\n";
        }
        if(!empty($this->cc)) {
            $mail .= "Cc:" . implode(", ", $this->cc)."\r\n";
        }
        if(!empty($this->bcc)) {
            $mail .= "Bcc:" . implode(", ", $this->bcc)."\r\n";
        }
        $mail .= "Subject:" . $this->subject . "\r\n\r\n";
        $mail .= $this->body . "\r\n.\r\n";

        $command = [];
        $command[] = array("HELO sendmail\r\n", 250);
        $command[] = array("AUTH LOGIN\r\n", 334);
        $command[] = array(base64_encode($this->username) . "\r\n", 334);
        $command[] = array(base64_encode($this->password) . "\r\n", 235);
        $command[] = array("MAIL FROM:<" . $this->from . ">\r\n", 250);
        foreach ($this->to as $to) {
            $command[] = array("RCPT TO:<" . $to . ">\r\n", 250);
        }
        foreach ($this->cc as $cc) {
            $command[] = array("RCPT TO:<" . $cc . ">\r\n", 250);
        }
        foreach ($this->bcc as $bcc) {
            $command[] = array("RCPT TO:<" . $bcc . ">\r\n", 250);
        }
        $command[] = array("DATA\r\n", 354);
        $command[] = array($mail, 250);
        $command[] = array("QUIT\r\n", 221);

        return $command;
    }

    /**
     * 发送命令
     * @access protected
     * @param string $command 发送到服务器的smtp命令
     * @param int $code 期望服务器返回的响应吗
     * @return boolean
     */
    protected function sendCommand($command, $code)
    {
        //echo 'Send command:' . $command . ',expected code:' . $code . '<br />';
        //发送命令给服务器
        try {
            if (socket_write($this->socket, $command, strlen($command))) {
                //读取服务器返回
                $data = trim(socket_read($this->socket, 1024));
                //echo 'response:' . $data . '<br /><br />';
                if ($data) {
                    $pattern = "/^" . $code . "/";
                    if (preg_match($pattern, $data)) {
                        return true;
                    } else {
                        $this->errorMessage = "Error:" . $data . "|**| command:";
                        return false;
                    }
                } else {
                    $this->errorMessage = "Error:" . socket_strerror(socket_last_error());
                    return false;
                }
            } else {
                $this->errorMessage = "Error:" . socket_strerror(socket_last_error());
                return false;
            }
        } catch (Exception $e) {
            $this->errorMessage = "Error:" . $e->getMessage();
        }
    }

    /**
     * 建立到服务器的连接
     * @return boolean
     */
    private function socket()
    {
        if (!function_exists("socket_create")) {
            $this->errorMessage = "Extension sockets must be enabled";
            return false;
        }
        //创建socket资源
        $this->socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
        if (!$this->socket) {
            $this->errorMessage = socket_strerror(socket_last_error());
            return false;
        }
        socket_set_block($this->socket);//设置阻塞模式
        //连接服务器
        if (!socket_connect($this->socket, $this->server, $this->port)) {
            $this->errorMessage = socket_strerror(socket_last_error());
            return false;
        }
        socket_read($this->socket, 1024);
        return true;
    }

    /**
     * 关闭socket
     * 其实这里也没必要关闭，smtp命令：QUIT发出之后，服务器就关闭了连接，本地的socket资源会自动释放
     * @return bool
     */
    private function close()
    {
        if (isset($this->socket) && is_object($this->socket)) {
            $this->socket->close();
        }
        return true;
    }
}
/**************************** Test ***********************************/
/*$mail = new Mail();
$mail->setServer("smtp.xxx.com", "XXXXX", "XXXXX");
$mail->setFrom("XXXXX@XXXXX");
$mail->setReceiver("XXXXX@XXXXX");
$mail->setCc("XXXXX@XXXXX");
$mail->setMailInfo("test", "test");
$mail->sendMail();*/