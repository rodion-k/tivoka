<?php
/**
 *	Tivoka - a JSON-RPC implementation for PHP
 *	Copyright (C) 2011  Marcel Klehr <marcel.klehr@gmx.de>
 *
 *	This program is free software; you can redistribute it and/or modify it under the 
 *	terms of the GNU General Public License as published by the Free Software Foundation;
 *	either version 3 of the License, or (at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 *	without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *	See the GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License along with this program;
 *	if not, see <http://www.gnu.org/licenses/>.
 *
 * @package Tivoka
 * @author Marcel Klehr <marcel.klehr@gmx.de>
 * @copyright (c) 2011, Marcel Klehr
 */
/**
 * Opens a connection to the given JSONJ-RPC server for invoking the provided remote procedures
 * @package Tivoka
 */
class Tivoka_ClientConnection
{
	/**
	 * @var ressource The ressource returned by fsockopen()
	 */
	public $connection;
	
	/**
	 * @var array The target, parsed by parse_url()
	 */
	public $target;
	
	/**
	 * Initializes a Tivoka_ClientConnection object
	 * @param string $target the URL of the target server (MUST include http scheme)
	 */
	public function __construct($target)
	{
		//validate url...
		if(!filter_var($target, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED))
			{ throw new InvalidArgumentException('Valid URL (scheme,domain[,path][,file]) required.'); return; }
		$this->target = parse_url($target);
		
		if($this->target['scheme'] !== 'http')
			{ throw new InvalidArgumentException('Unknown or unsupported scheme given: \''.htmlspecialchars($this->target['url']).'\''); return; }
		
		//connecting...
		$this->connection = fsockopen($this->target['host'], 80, $errno, $errstr);
		if(!$this->connection)	throw new InvalidArgumentException('Cannot connect to the given URL (\'fsockopen\' failed)');
	}
	
	public function __destruct()
	{
		fclose($this->connection);
	}
	
	/**
	 * Sends a JSON-RPC request to the defined target
	 * @param Tivoka_ClientRequest $request A Tivoka request
	 * @see Tivoka_ClientResponse
	 * @return mixed Depends on the given request object
	 */
	public function send(Tivoka_ClientRequest $request)
	{
		$json = $request->getRequest();
		//preparing...
		$get = "GET ".$this->target['path']." HTTP/1.1\r\n"
			. "Host: ".$this->target['host']."\r\n"
			. "Content-Type: application/json\r\n"
			. "Content-Length: ".strlen($json)."\r\n"
			. "Connection: Close\r\n\r\n"
			. $json;
		
		//sending...
		if(fwrite($this->connection, $get, strlen($get)) === 0)
		{
			return $request->processError(Tivoka_ClientResponse::ERROR_CONNECTION_FAILED);
		}
		
		//receiving response...
		stream_set_timeout ($this->connection, 10);
		$httpresp = stream_get_contents($this->connection);
		if($httpresp === FALSE)
		{
			return $request->processError(Tivoka_ClientResponse::ERROR_CONNECTION_FAILED);
		}
		
		if(strpos(substr($httpresp,0,50),'404 Not Found') !== FALSE)
		{
			return $request->processError(Tivoka_ClientResponse::ERROR_HTTP_NOT_FOUND);
		}
		
		$response = '';
		if(strpos($httpresp,"\r\n\r\n") !== FALSE)
		{
			list(,$response) = explode("\r\n\r\n",$httpresp,2);
		}
		
		return $request->getResponse($response);
	}
}
?>