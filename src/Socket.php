<?php
declare(strict_types=1);
namespace Zodream\Http;
/**
* socket 
* 
* @author Jason
*/

class Socket {
	protected mixed $socket = null;
	
	protected string $ip = '127.0.0.1';
	
	protected int $port = 80;
    
    public function __construct(string $ip, int $port = 0) {
        if (!empty($ip) && !empty($port)) {
            $this->setIpAddress($ip, $port);
        }
    }

    /**
     * SET IP AND PORT
     * @param string $ip
     * @param int $port
     * @return $this
     */
    public function setIpAddress(string $ip, int $port = 80) {
        if (str_contains($ip, ':')) {
            list($ip, $port) = explode(':', $ip);
        }
        if (empty($ip)) {
            $ip = '127.0.0.1';
        }
        $this->ip = $ip;
        $this->port = intval($port);
        return $this;
    }

    /**
	 * 创建
	 */
	public function create() {
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        return $this;
	}

    /**
     * 服务端
     * @param int $backlog
     * @return Socket
     */
	public function listen(int $backlog = 4) {
		socket_bind($this->socket, $this->ip, $this->port);
		socket_listen($this->socket, $backlog); // 4条等待
        return $this;
	}
	
	/**
	 * 客户端
	 */
	public function connect() {
		socket_connect($this->socket, $this->ip, $this->port);
        return $this;
	}
	
	/**
	 * 接受，需要循环接受 接受到信息后 read()
	 */
	public function accept() {
		socket_accept($this->socket);
        return $this;
	}

    /**
     * 取出信息
     * @param int $length
     * @return string
     */
	public function read(int $length = 8192) {
		$content = '';
		while ($buff = socket_read($this->socket, $length)) {
			$content .= $buff;
		}
		return $content;
	}
	
	/**
	 * 发送信息
	 * @param string $content
	 */
	public function write(string $content) {
	    socket_write($this->socket, $content);
        return $this;
	}
		
	/**
	 * 关闭
	 */
	public function close() {
		socket_close($this->socket);
        return $this;
	}
	
	/**
	 * 获取上一天错误信息
	 */
	public function getError(): string {
		return socket_strerror(socket_last_error($this->socket));
	}

}