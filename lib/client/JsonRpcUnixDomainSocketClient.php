<?php
function __autoload($className) {
	include_once($className.".php");
}
class JsonRpcUnixDomainSocketClient {
	private $_socketName;

	public function __construct($socketName) {
		$this->_socketName = $socketName;
	}
	public function __call($name, $arguments) {
		return $this->call(new RpcRequest($name,$arguments));
	}
	public function call($rpcRequest) {
		if($rpcRequest instanceof RpcRequest) {
			return $this->unixDomainSocketRequest($rpcRequest->getRpcRequestObject());
		}
	}
	public function callBatch($rpcRequestList) {
        $rpcBatchArray = array();
        foreach($rpcRequestList as $rpcRequest) {
            if($rpcRequest instanceof RpcRequest) {
                array_push($rpcBatchArray, $rpcRequest->getRpcRequestObject());
            }
        }
        return $this->unixDomainSocketRequest($rpcBatchArray);
	}
	private function unixDomainSocketRequest($rpcBatchArray) {
	$jsonContent = json_encode($rpcBatchArray);
	$jsonContent .= PHP_EOL;
	$sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
	if ($sock === false) {
		throw new Exception("Can't create socket", 1);
	}
	if(! socket_connect($sock, $this->_socketName)) {
		throw new Exception("Can't connect  socket " . $this->_socketName, 1);
	}
	
	$toSend = $jsonContent;
	$everythingWriten = False;
	do {
	$len = strlen($toSend);
	$bytes_sent = socket_write($sock, $toSend, $len);
	if($bytes_sent === false) {
		throw new Exception("Impossible to send data in socket " . $this->_socketName, 1);
	}
	if($bytes_sent < $len) {
		$everythingWritten = False;
		$toSend = substr($toSend, $bytes_sent, $len - $bytes_sent);
		//throw new Exception("Only " .  $bytes_sent . " bytes sent. Expected " . $len . "\n", 1);
	}
	else {
		$everythingWritten = True;
	}
	} while(!$everythingWritten);

	$response = "";
	do {
		$recv = "";
		$bytes_recv = socket_recv($sock, $recv, 512, MSG_WAITALL);
		if($bytes_recv === false) { 
			throw new Exception("Impossible to receive data in socket " . $this->_socketName, 1);
		}
		$response .= substr($recv, 0, $bytes_recv);
	} while(strpos($response, PHP_EOL) === False);
	socket_close($sock);

        $json_response = json_decode($response);
        if (json_last_error() != JSON_ERROR_NONE) {
            switch (json_last_error()) {
                case JSON_ERROR_DEPTH:
                    $message = 'The maximum stack depth has been exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $message = 'Invalid or malformed JSON';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $message = 'Control character error, possibly incorrectly encoded';
                    break;
                case JSON_ERROR_SYNTAX:
                    $message = 'Syntax error';
                    break;
                case JSON_ERROR_UTF8:
                    $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    $message = "Error decoding JSON string.";
                    break;
            }
            $message .= "\nMethod: " . $rpcBatchArray->method.
                        "\nParams: " . var_export($rpcBatchArray->params, TRUE).
                        "\nResponse: " . $response;
            throw new Exception($message, json_last_error());
        }
        return $json_response;
	}
}
