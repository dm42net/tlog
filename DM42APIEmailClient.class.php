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

require_once (dirname(__FILE__)."/DM42APIClient.class.php");

Class DM42APIEmailClient extends DM42APIClient{

    public function sendEmail($DM42Message/*, $use_raw_request = false*/) {

        $this->setPath("");
        
		if(!$DM42Message->validate()) {
			return false;
		}

		$rest = new DM42APIClientRequest($this, 'POST');
/*
		$action = !empty($sesMessage->attachments) || $use_raw_request ? 'SendRawEmail' : 'SendEmail';
		$rest->setParameter('Action', $action);

		if($action == 'SendRawEmail') {
			$rest->setParameter('RawMessage.Data', $sesMessage->getRawMessage());
		} else {
*/
        $this->setPath("/email/send");
	$messagecontainer=$DM42Message->getMessageContainer();
        $rest->setParameter("message_container",$DM42Message->getMessageContainer());
        
        
        
/*		}      */

		$response = $rest->getResponse();
        
		//$response['RequestId'] = (string)$rest->body->ResponseMetadata->RequestId;
		return $response;
	}

}


class DM42APIEmailMessage {

    private $to;
    private $cc;
    private $bcc;
    private $replyto;
    private $from;
    private $returnpath;
    private $subject;
    private $html;
    private $text;
    private $messageid;
    private $errors;
    private $meta;

    /**
     * Encode recipient with the specified charset in `recipientsCharset`
     *
     * @param  string|array $recipient Single recipient or array of recipients
     * @return string            Encoded recipients joined with comma
     */

    public function encodeRecipients($recipient)
    {
        if (is_array($recipient)) {
            return join(', ', array_map(array($this, 'encodeRecipients'), $recipient));
        }

        //If non-ascii then encode, otherwise, move on frog.
        if (preg_match("/(.*)([^\x20-\x7f])(.*)<(.*)>/", $recipient, $regs)) {
            $recipient = '=?' . $this->recipientsCharset . '?B?'.base64_encode($regs[1]).'?= <'.$regs[4].'>';
        }
        return $recipient;

    }
    
    private function addError ($error,$description) {
        $this->errors[]=Array("$error"=>"$description");
    }

    public function getErrors () {
        return $this->errors;
    }

    private function filter_address($address) {
        $filtered=null;
        $filtered=$this->encodeRecipients($address);
        if (preg_match("/(.*)<(.*)>/", $filtered, $regs)) {
            $recipientAddress=$regs[2];
        } else {
            $recipientAddress=$filtered;
        }

        if ('' == filter_var($recipientAddress, FILTER_SANITIZE_EMAIL)) {
            $this->addError ("INVALID_ADDRESS","ERROR: $address is invalid and cannot be included.  Message will be sent to remaining valid recipients.");
            return false;
        }

        return $filtered;

    }

    private function filter_addresses($addresses) {
        $filtered=Array();
        if (is_array($addresses)) {
            foreach ($addresses as $address) {
              $newaddress=$this->filter_addres($address);
              if ($newaddress) {
                  $filtered[]=$newaddress;
              }
            }
        } else {
            $newaddress=$this->filter_address($addresses);
            if ($newaddress) {
                $filtered[]=$newaddress;
            }
        }
        return $filtered;
    }

    public function addTo ($newto) {
        $addresses=$this->filter_addresses($newto);
        if (count($addresses)) {
            $this->to=array_unique(array_merge($addresses,$this->to));
            return true;
        }
        return false;
    }
    
    public function addCC ($newcc) {
        $addresses=$this->filter_addresses($newcc);
        if (count($addresses)) {
            $this->cc=array_unique(array_merge($addresses,$this->cc));
            return true;
        }
        return false;
    }
    
    public function addBCC ($newbcc) {
        $addresses=$this->filter_addresses($newbcc);
        if (count($addresses)) {
            $this->bcc=array_unique(array_merge($addresses,$this->bcc));
            return true;
        }
        return false;
    }
    
    public function addReplyTo ($newbreplyto) {
        $addresses=$this->filter_addresses($newreplyto);
        if (count($addresses)) {
            $this->replyto=array_unique(array_merge($addresses,$this->replyto));
            return true;
        }
        return false;
    }
    
    public function addFrom ($newfrom) {
        $this->from=$this->filter_address($newfrom);
    }
    
    public function addReturnPath ($newreturnpath) {
        $this->returnpath=$this->filter_address($returnpath);
    }
    
    public function addSubject ($newsubject) {
        $this->subject=$newsubject;
    }
    
    public function addHtml ($newhtml) {
        $this->html=$newhtml;
    }
    
    public function addText ($newtext) {
        $this->text=$newtext;
    }

    public function __construct () {
      $this->to=Array();
      $this->cc=Array();
      $this->bcc=Array();
      $this->replyto=Array();
      $this->from='';
      $this->returnpath='';
      $this->subject='';
      $this->html=null;
      $this->text=null;
      $this->errors=Array();
      $this->meta=Array();
   }

    public function getMessageContainer () {

        $container=Array(
            "From"=>$this->from,
            "ReplyTo"=>$this->replyto,
            "ReturnPath"=>$this->returnpath,
            "To"=>$this->to,
            "CC"=>$this->cc,
            "BCC"=>$this->bcc,
            "Subject"=>$this->subject,
            "text"=>$this->text,
            "html"=>$this->html                        
        );
    return $container;
    }
    
   public function addressCount () {
       $to=$this->to;
       $cc=$this->cc;
       $bcc=$this->bcc;
       $addresses = array_merge($to,$cc,$bcc);
       return count($addresses);
   }

	/**
	* Validates whether the message object has sufficient information to submit a request to SES.
	* This does not guarantee the message will arrive, nor that the request will succeed;
	* instead, it makes sure that no required fields are missing.
	*
	* This is used internally before attempting a to store the email,
	* but it can be used outside of this file if verification is desired.
	* May be useful if e.g. the data is being populated from a form; developers can generally
	* use this function to verify completeness instead of writing custom logic.
	*
	* @return boolean
	*/
    public function validate () {
        if ( 1 > $this->addressCount() ) {
            $this->addError ("NO_RECIPIENTS","No valid recipients were found for message to sent.");
            return false;
        }

        if ( $this->from == null || $this->from == '') {
            $this->addError ("MISSING_FROM","Valid FROM address is required.");
            return false;
        }
        
        if (( !isset($this->subject) || $this->subject == '' ) &&
            ( !isset($this->text) || $this->text == '' ) &&
            ( !isset($this->html) || $this->html == ''))  {
            $this->addError ("MISSING_CONTENT","Message must contain one of: subject, text, html.");
            return false;
        }

        return true;
    }

}

