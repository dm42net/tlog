<?php

/*
*  Client Classes for DM42Net API services
*     including the XPSend Email Service.
*/



/**
*
* Copyright (c) 2016, Matthew Dent, DM42.Net.
* Parts copyright (c) 2014, Daniel Zahariev.
* Parts copyright (c) 2011, Dan Myers.
* Parts copyright (c) 2008, Donovan Schonknecht.
* All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
* - Redistributions of source code must retain the above copyright notice,
*   this list of conditions and the following disclaimer.
* - Redistributions in binary form must reproduce the above copyright
*   notice, this list of conditions and the following disclaimer in the
*   documentation and/or other materials provided with the distribution.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
* AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
* IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
* ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
* LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
* SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
* INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
* CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
* ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
* This is a modified BSD license (the third clause has been removed).
* The BSD license may be found here:
* http://www.opensource.org/licenses/bsd-license.php
*
* Based in part on SimpelEmailService PHP classes developed by Daniel Zahariev.
* https://github.com/daniel-zahariev/php-aws-ses
*
* Amazon Simple Email Service is a trademark of Amazon.com, Inc. or its affiliates.
*
* SimpleEmailService is based on Donovan Schonknecht's Amazon S3 PHP class, found here:
* http://undesigned.org.za/2007/10/22/amazon-s3-php-class
*
* @copyright 2016 Matthew Dent, DM42.Net
* @copyright 2014 Daniel Zahariev
* @copyright 2011 Dan Myers
* @copyright 2008 Donovan Schonknecht
*/

Class DM42APIClient {
    protected $accessKey;
    protected $secretKey;
    protected $host;
    protected $apiprefix;
    protected $apipath;

    public function getRequestPath() { return $this->apiprefix.$this->apipath; }
    public function getAccessKey() { return $this->accessKey; }
	public function getSecretKey() { return $this->secretKey; }
	public function getHost() { return $this->host; }

    protected $verifyHost = true;
	protected $verifyPeer = true;

	// verifyHost and verifyPeer determine whether curl verifies ssl certificates.
	// It may be necessary to disable these checks on certain systems.
	// These only have an effect if SSL is enabled.
	public function verifyHost() { return $this->verifyHost; }
	public function enableVerifyHost($enable = true) { $this->verifyHost = $enable; }

	public function verifyPeer() { return $this->verifyPeer; }
	public function enableVerifyPeer($enable = true) { $this->verifyPeer = $enable; }

 	/**
	* Constructor
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @param string $host DM42API endpoint
	* @return void
	*/
	public function __construct($accessKey = null, $secretKey = null, $host = 'api.xpsend.com', $prefix = "/api/v1") {
		if ($accessKey !== null && $secretKey !== null) {
			$this->setAuth($accessKey, $secretKey);
		}
        $this->setPrefix($prefix);
		$this->host = $host;

        if (defined("DM42APIDEBUG") && !defined("DM42APIDEBUGHTTPS")) {
            $this->$verifyHost = false;
            $verifyPeer = false;
        }
	}

	/**
	* Set access key and secret key
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @return API $this
	*/
	public function setAuth($accessKey, $secretKey) {
		$this->accessKey = $accessKey;
		$this->secretKey = $secretKey;

		return $this;
	}

    public function setPrefix($prefix) {
        $this->apiprefix = $prefix."/".$this->getAccessKey();
        return $this;
    }
    
    public function setPath($path) {
        $this->apipath = $path;
        return $this;
    }

}


Class DM42APIClientRequest {
    
    private $req, $verb, $parameters = array();
	public $response;
	public static $curlOptions = array();


	/**
	* Constructor
	*
	* @param string $req The Request object making this request
	* @param string $verb HTTP verb
	* @return void
	*/
    function __construct($req, $verb) {
		$this->req = $req;
		$this->verb = $verb;
		$this->response = (object) array('body' => '', 'code' => 0, 'error' => false);
	}

    /**
	* Set request parameter
	*
	* @param string  $key Key
	* @param string  $value Value
	* @param boolean $replace Whether to replace the key if it already exists (default true)
	* @return Request $this
	*/
	public function setParameter($key, $value, $replace = true) {
		if(!$replace && isset($this->parameters[$key]))
		{
			$temp = (array)($this->parameters[$key]);
			$temp[] = $value;
			$this->parameters[$key] = $temp;
		}
		else
		{
			$this->parameters[$key] = $value;
		}

		return $this;
	}

	/**
	* Get the response
	*
	* @return object | false
	*/
	public function getResponse() {

		$params = array();
		foreach ($this->parameters as $var => $value)
		{
            $params["$var"] = $value;
		}

		// must be in format 'Sun, 06 Nov 1994 08:49:37 GMT'
		$date = gmdate('D, d M Y H:i:s e');

		$query = json_encode($params);

		$headers = array();
		$headers[] = 'Date: ' . $date;
		$headers[] = 'Host: ' . $this->req->getHost();
        $headers[] = "Content-type: application/json";
        $headers[] = 'X-DM42API-REQDate: ' . $date;
		$headers[] = 'X-DM42APISignature: ' . $this->getSignature($date);

        if (!DM42APIDEBUG || DM42APIDEBUGHTTPS) {
            $url = 'https://'.$this->req->getHost().$this->req->getRequestPath();
        } else {
            $url = 'http://'.$this->req->getHost().$this->req->getRequestPath();
        }

		// Basic setup
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, 'DM42APIClient1.0/php');

		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, ($this->req->verifyHost() ? 2 : 0));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, ($this->req->verifyPeer() ? 1 : 0));

		// Request types
		switch ($this->verb) {
			case 'GET':
				$url .= '?'.$query;
				break;
			case 'POST':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
			break;
			default: break;
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER, false);

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, 'responseWriteCallback'));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		foreach(self::$curlOptions as $option => $value) {
			curl_setopt($curl, $option, $value);
		}
// SD (option ?)
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		// Execute, grab errors
		if (curl_exec($curl)) {
			$this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		} else {
			$this->response->error = array(
				'curl' => true,
				'code' => curl_errno($curl),
				'message' => curl_error($curl)
			);
            return json_encode(
                Array
                    ("status"=>"ERROR",
                     "errors"=>Array(
                        Array(
                            "SEND_FAILED"=>"Could not connect to provider or processing failed before completion.",
                            "CURL_INFO"=>$this->response->error
                        )
                     )
                )
            );
		}

		@curl_close($curl);

		// Pass body back to caller
        return $this->response->body;
	}

    	/**
	* CURL write callback
	*
	* @param resource $curl CURL resource
	* @param string $data Data
	* @return integer
	*/
	private function responseWriteCallback(&$curl, &$data) {
        $this->response->body .= $data;
		return strlen($data);
	}

  	/**
	* Generate the auth string using Hmac-SHA256
	*
	* @internal Used by SimpleDBRequest::getResponse()
	* @param string $string String to sign
	* @return string
	*/
	private function getSignature($string) {
		return md5($this->req->getSecretKey().$string);
	}    
}
