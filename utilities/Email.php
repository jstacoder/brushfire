<?
///class used to send email
/**
Uses:
@verbatim
Statically
	email::send(HTML,to,subject,from,options);
	email::send(array('html'=>HTML,'text'=>TEXT),to,subject,from,options);
Use with attachments:
	$email = new Email(HTML);
	$email->attach('/var/www/humval/imgs/crystal.png');
Use in a chained way
	(new Email("HTML","TEXT"))->attach('/var/log/test.log')->send('spamme@capob.com','The state of bob','spamme@capob.com');
@endverbatim
*/
class Email{
	function __construct($html='',$text=null){
		$this->html = $html;
		$this->text = $text;
		$this->attached = array();
	}
	/**assumes first argument to be Email object or argument of either:
		string html email
		array('html'=>html,'text'=>text)
	*/
	static function __callStatic($name,$arguments){
		$email = array_shift($arguments);
		if(!is_a($email,__CLASS__)){
			if(is_array($email)){
				$email = new Email($email['html'],$email['text']);
			}else{
				$email = new Email($email);
			}
		}
		return call_user_func_array(array($email,$name),$arguments);
	}
	function __call($name,$arguments){
		return call_user_func_array(array($this,$name),$arguments);
	}
	//rfc 5322, 5321, text line no longer than 1000; mtas thusly insert newlines, screwing up dkim signing.  So, insert newlines prior to handing to mta.
	static function applyLineLengthMax($text){
		$text = preg_replace('@[^\n]{80,500}? @','$0'."\n",$text);
		$text = preg_replace('@[^\n]{500}@','$0'."\n",$text);
		return $text;
	}
	///send an email
	/**
	see callStatic for special static handling
	
	@param	to	to address "bob <bob@bobery.com>, joe_man@susan.com"
	@param	subject	subject of email
	@param	from	from email address
	@options	array of options including bcc, cc.
	*/
	private function send($to,$subject,$from=null,$options=null){
		if($this->html){
			$this->html = self::applyLineLengthMax(utf8_encode($this->html));
		}
		if($this->text){
			$this->text = self::applyLineLengthMax(utf8_encode($this->text));
		}
		if($this->attached){
			$boundary = Config::$x['emailUniqueId'].'-A-'.microtime(true);
			$this->header['Content-Type'] = 'multipart/mixed; boundary="'.$boundary.'"';
			$boundary = "\n\n--".$boundary."\n";
			
			if($this->html && $this->text){
				$message .= $boundary;
				$alterBoundary = Config::$x['emailUniqueId'].'-B-'.microtime(true);
				$message .= 'Content-Type: multipart/alternative; boundary="'.$alterBoundary.'"';
				$alterBoundary = "\n\n--".$alterBoundary."\n";
				
				$message .= $alterBoundary.
					'Content-type: text/plain; charset="UTF-8"'."\n".
					'Content-Transfer-Encoding: 8bit'."\n\n".
					$this->text;
				
				$message .= $alterBoundary.
					'Content-Type: text/html; charset="UTF-8"'."\n".
					'Content-Transfer-Encoding: 8bit'."\n\n".
					$this->html;
			}elseif($this->text){
				$message .= $boundary.
					'Content-type: text/plain; charset="UTF-8"'."\n".
					'Content-Transfer-Encoding: 8bit'."\n\n".
					$this->text;
			}elseif($this->html){
				$message .= $boundary.
					'Content-type: text/plain; charset="UTF-8"'."\n".
					'Content-Transfer-Encoding: 8bit'."\n\n".
					$this->html;
			}
			
			foreach($this->attached as $k=>$v){
				if(is_file($v)){
					$data = chunk_split(base64_encode(file_get_contents($v)));
					
					$finfo = finfo_open(FILEINFO_MIME_TYPE);
					$mime = finfo_file($finfo, $v);
					finfo_close($finfo);
					
					if(!Tool::isInt($k)){
						$name = $k;
					}else{
						$name = basename($v);
					}
					$message .= $boundary.
						'Content-Type: '.$mime.'; name="'.$name.'"'."\n".
						'Content-Transfer-Encoding: base64'."\n".
						'Content-Disposition: attachment'."\n\n".
						$data;
				}
			}
		}elseif($this->html && $this->text){
			$boundary = Config::$x['emailUniqueId'].'-A-'.microtime(true);
			$this->header['Content-Type'] = 'multipart/alternative; boundary="'.$boundary.'"';
			$boundary = "\n\n--".$boundary."\n";
			
			$message .= $boundary.
				'Content-type: text/plain; charset="UTF-8"'."\n".
				'Content-Transfer-Encoding: 8bit'."\n\n".
				$this->text;
			
			$message .= $boundary.
				'Content-Type: text/html; charset="UTF-8"'."\n".
				'Content-Transfer-Encoding: 8bit'."\n\n".
				$this->html;
		}elseif($this->html){
			$this->header['Content-Type'] = 'text/html; charset=UTF-8';
			$message = $this->html;
		}else{
			$this->header['Content-Type'] = 'text/plain; charset=UTF-8';
			$message = $this->text;
		}
		
		if($from){
			$this->header['From'] = $this->header['Reply-To'] = $from;
			preg_match('#[a-z0-9.\-]+@[a-z0-9.\-]+\.[a-z]+#i',$from,$match);
			$this->header['Return-Path'] = '<'.$match[0].'>';
			$params .= '-f'.$match[0];
		}
		if($options){
			if($options['cc']){
				$this->header['Cc'] = $options['cc'];
			}
			if($options['bcc']){
				$this->header['Bcc'] = $options['bcc'];
			}
		}
		
		$this->header['Message-Id'] = '<'.Config::$x['emailUniqueId'].'-'.sha1(uniqid(microtime()).rand(1,100)).'@'.gethostname().'>';
		foreach($this->header as $k=>$v){
			#not separating with \r\n because this seems to cause DKIM hash to fail
			$headers .= $k.': '.$v."\n";
		}
		if(is_array($to)){
			$to = implode(', ',$to);
		}
		mail($to,$subject,$message,$headers,$params);
		return $this->header['Message-Id'];
	}
	///attach a file to an email object
	/**
	@param	file	path to file
	*/
	private function attach($file,$name=null){
		if($name){
			$this->attached[$name] = $file;
		}else{
			$this->attached[] = $file;
		}
		return $this;
	}
}