<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;
use InvalidArgumentException;

use Zend\Mail\Storage\Writable\Maildir;
use Zend\Mail\Message;
use Symfony\Component\Yaml\Yaml;

use TheFox\Imap\Exception\NotImplementedException;
use TheFox\Logger\Logger;
use TheFox\Logger\StreamHandler;
use TheFox\Network\Socket;

class Server extends Thread{
	
	const LOOP_USLEEP = 10000;
	
	private $log;
	private $isListening = false;
	private $ip;
	private $port;
	private $clientsId = 0;
	private $clients = array();
	private $storages = array();
	
	public function __construct($ip = '127.0.0.1', $port = 143){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		$this->log = new Logger('imapserver');
		$this->log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		#$this->log->pushHandler(new StreamHandler('log/imapserver.log', Logger::DEBUG));
		
		$this->log->info('start');
		
		$this->setIp($ip);
		$this->setPort($port);
	}
	
	public function getLog(){
		return $this->log;
	}
	
	public function setIp($ip){
		$this->ip = $ip;
	}
	
	public function setPort($port){
		$this->port = $port;
	}
	
	public function getStorages(){
		return $this->storages;
	}
	
	public function getRootStorage(){
		$this->storageInit();
		return $this->storages[0]['object'];
	}
	
	public function getRootStorageDbMsgIdBySeqNum($seqNum){
		if($this->storages[0]['db']){
			#print __CLASS__.'->'.__FUNCTION__.' seqNum: '.$seqNum."\n";
			$uid = $this->storages[0]['object']->getUniqueId($seqNum);
			#print __CLASS__.'->'.__FUNCTION__.' uid: '.$uid."\n";
			return $this->storages[0]['db']->getMsgIdByUid($uid);
		}
		
		return null;
	}
	
	public function getRootStorageDbNextId(){
		if($this->storages[0]['db']){
			return $this->storages[0]['db']->getNextId();
		}
		
		return null;
	}
	
	public function init(){
		if($this->ip && $this->port){
			$this->log->notice('listen on '.$this->ip.':'.$this->port);
			
			$this->socket = new Socket();
			
			$bind = false;
			try{
				$bind = $this->socket->bind($this->ip, $this->port);
			}
			catch(Exception $e){
				$this->log->error($e->getMessage());
			}
			
			if($bind){
				try{
					if($this->socket->listen()){
						$this->log->notice('listen ok');
						$this->isListening = true;
						
						return true;
					}
				}
				catch(Exception $e){
					$this->log->error($e->getMessage());
				}
			}
			
		}
		
		throw new RuntimeException('Could not listen.');
	}
	
	public function run(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		#print __CLASS__.'->'.__FUNCTION__.': client '.count($this->clients)."\n";
		
		$readHandles = array();
		$writeHandles = null;
		$exceptHandles = null;
		
		if($this->isListening){
			$readHandles[] = $this->socket->getHandle();
		}
		foreach($this->clients as $clientId => $client){
			// Collect client handles.
			$readHandles[] = $client->getSocket()->getHandle();
			
			// Run client.
			#print __CLASS__.'->'.__FUNCTION__.': client run'."\n";
			$client->run();
		}
		$readHandlesNum = count($readHandles);
		
		$handlesChanged = $this->socket->select($readHandles, $writeHandles, $exceptHandles);
		#$this->log->debug('collect readable sockets: '.(int)$handlesChanged.'/'.$readHandlesNum);
		
		if($handlesChanged){
			foreach($readHandles as $readableHandle){
				if($this->isListening && $readableHandle == $this->socket->getHandle()){
					// Server
					$socket = $this->socket->accept();
					if($socket){
						$client = $this->clientNew($socket);
						$client->sendHello();
						#$client->sendPreauth('IMAP4rev1 server logged in as thefox');
						#$client->sendPreauth('server logged in as thefox');
						
						#$this->log->debug('new client: '.$client->getId().', '.$client->getIpPort());
					}
				}
				else{
					// Client
					$client = $this->clientGetByHandle($readableHandle);
					if($client){
						if(feof($client->getSocket()->getHandle())){
							$this->clientRemove($client);
						}
						else{
							#$this->log->debug('old client: '.$client->getId().', '.$client->getIpPort());
							$client->dataRecv();
							
							if($client->getStatus('hasShutdown')){
								$this->clientRemove($client);
							}
						}
					}
					
					#$this->log->debug('old client: '.$client->getId().', '.$client->getIpPort());
					
				}
			}
		}
	}
	
	public function loop(){
		$s = time();
		$r1 = 0;
		$r2 = 0;
		
		while(!$this->getExit()){
			$this->run();
			
			if(time() - $s >= 0 && !$r1){
				$r1 = 1;
				
				try{
					$this->storages[0]['object']->createFolder('test2');
				}
				catch(Exception $e){}
				
				$message = new Message();
				$message->addFrom('thefox21at@gmail.com');
				$message->addTo('thefox@fox21.at');
				$message->setSubject('test '.date('Y/m/d H:i:s'));
				$message->setBody('body');
				
				#$this->mailAdd($message->toString(), 'test2', null, true);
				#$this->mailAdd($message->toString(), null, array(), true);
				
				
				
				#$mailboxPath = './tmp_mailbox_'.mt_rand(1, 9999999);
				#$mailboxPath = './tmp_mailbox';
				#$this->dirDelete('mailbox');
				#Maildir::initMaildir($mailboxPath);
				#$this->storageAdd(new Maildir(array('dirname' => $mailboxPath)), $mailboxPath, 'temp');
				#$this->storageAddMaildir($mailboxPath);
				
				/*
				try{
					
					$this->storages[0]['object']->createFolder('test123.x');
					$this->storages[0]['object']->createFolder('test123.x.a');
					$this->storages[0]['object']->createFolder('test123.y');
					$this->storages[0]['object']->createFolder('test123.z');
					$this->storages[0]['object']->createFolder('test123.z.b');
				}
				catch(Exception $e){
					$this->log->error('createFolder: '.$e->getMessage());
				}
				*/
				
			}
			
			if(time() - $s >= 2 && !$r2){
				$r2 = 1;
				
				$message = new Message();
				$message->addFrom('thefox21at@gmail.com');
				$message->addTo('thefox@fox21.at');
				$message->setSubject('test '.date('Y/m/d H:i:s'));
				$message->setBody('body');
				
				#$this->mailAdd($message->toString(), null, null, true);
				#$this->mailAdd($message->toString(), null, null, true);
			}
			
			usleep(static::LOOP_USLEEP);
		}
		
		$this->shutdown();
	}
	
	public function shutdown(){
		$this->log->debug('shutdown');
		
		// Notify all clients.
		foreach($this->clients as $clientId => $client){
			$client->sendBye('Server shutdown');
			$this->clientRemove($client);
		}
		
		// Remove all temp files and save dbs.
		foreach($this->storages as $storage){
			if($storage['object'] instanceof Maildir){
				if($storage['type'] == 'temp'){
					$this->dirDelete($storage['path']);
					if($storage['db']){
						unlink($storage['db']->getFilePath());
					}
				}
				else{
					if($storage['db']){
						$storage['db']->save();
					}
				}
			}
		}
		
	}
	
	private function clientNew($socket){
		$this->clientsId++;
		#print __CLASS__.'->'.__FUNCTION__.' ID: '.$this->clientsId."\n";
		
		$client = new Client();
		$client->setSocket($socket);
		$client->setId($this->clientsId);
		$client->setServer($this);
		
		$this->clients[$this->clientsId] = $client;
		#print __CLASS__.'->'.__FUNCTION__.' clients: '.count($this->clients)."\n";
		
		return $client;
	}
	
	private function clientGetByHandle($handle){
		foreach($this->clients as $clientId => $client){
			if($client->getSocket()->getHandle() == $handle){
				return $client;
			}
		}
		
		return null;
	}
	
	private function clientRemove(Client $client){
		$this->log->debug('client remove: '.$client->getId());
		
		$client->shutdown();
		
		$clientsId = $client->getId();
		unset($this->clients[$clientsId]);
	}
	
	private function storageInit(){
		if(!$this->storages){
			$mailboxPath = './tmp_mailbox_'.mt_rand(1, 9999999);
			$this->storageAddMaildir($mailboxPath, 'temp');
		}
	}
	
	public function storageAdd($storage, $path, $type = 'normal', $db = null){
		if($storage instanceof Maildir){
			$this->storages[] = array(
				'object' => $storage,
				'path' => $path,
				'type' => $type,
				'db' => $db,
			);
			#ve($this->storages);
		}
		else{
			throw new NotImplementedException(''.( is_object($storage) ? 'Class '.get_class($storage) : 'Type '.gettype($storage) ).' not implemented yet.');
		}
	}
	
	public function storageAddMaildir($path, $type = 'normal'){
		if(!file_exists($path)){
			try{
				Maildir::initMaildir($path);
			}
			catch(Exception $e){
				$this->log->error('initMaildir: '.$e->getMessage());
			}
		}
		
		try{
			$dbpath = $path;
			if(substr($dbpath, -1) == '/'){
				$dbpath = substr($dbpath, 0, -1);
			}
			$db = new MsgDb($dbpath.'.msgs.yml');
			$db->load();
			
			$this->storageAdd(new Maildir(array('dirname' => $path)), $path, $type, $db);
		}
		catch(Exception $e){
			$this->log->error('storageAddMaildir: '.$e->getMessage());
		}
	}
	
	public function storageFolderAdd($path){
		$this->storageInit();
		
		foreach($this->storages as $storage){
			if($storage['object'] instanceof Maildir){
				$storage['object']->createFolder($path);
			}
		}
	}
	
	public function mailAdd($mail, $folder = null, $flags = array(), $recent = true){
		$this->storageInit();
		
		$uid = null;
		foreach($this->storages as $storage){
			if($storage['object'] instanceof Maildir){
				$storage['object']->appendMessage($mail, $folder, $flags, $recent);
				
				if($storage['db']){
					// Because of ISSUE 6317 (https://github.com/zendframework/zf2/issues/6317) in the Zendframework we must reselect the current folder.
					$oldFolder = $storage['object']->getCurrentFolder();
					#print "old: $oldFolder\n";
					$storage['object']->selectFolder($folder);
					
					$lastId = $storage['object']->countMessages();
					#$message = $storage['object']->getMessage($lastId);
					
					try{
						$uid = $storage['object']->getUniqueId($lastId);
						$storage['db']->msgAdd($uid);
						#print "uid: $uid\n";
					}
					catch(Exception $e){
						#print "ERROR: ".$e->getMessage()."\n";
					}
					
					
					#ve($message);
					#ve($uid);
					#ve($storage['object']->getUniqueId($uid));
					#ve($storage['object']->getUniqueId());
					#ve($storage['object']->countMessages());
					#ve($storage['object']->getNumberByUniqueId($uid));
					#ve($storage['object']->getNumberByUniqueId('1400770771.1713.22565.imac.home,S=129'));
					#ve($storage['object']->getNumberByUniqueId('1400770771.1713.22565.imac.home,S=129:2,S'));
					
					
					
					$storage['object']->selectFolder($oldFolder);
					
					#ve($storage['object']->getUniqueId());
				}
			}
		}
		
	}
	
	public function mailRemove($seqNum){
		$this->storageInit();
		
		$this->getRootStorage()->removeMessage($seqNum);
		
		foreach($this->storages as $storage){
			if($storage['object'] instanceof Maildir){
				if($storage['db']){
					$id = $this->getRootStorageDbMsgIdBySeqNum($seqNum);
					$storage['db']->msgRemove($id);
				}
			}
		}
	}
	
	private function dirDelete($path){
		if(is_dir($path)){
			$dh = opendir($path);
			if($dh){
				while(($file = readdir($dh)) !== false){
					if($file != '.' && $file != '..'){
						$this->dirDelete($path.'/'.$file);
					}
				}
				closedir($dh);
				
				@rmdir($path);
			}
		}
		else{
			@unlink($path);
		}
	}
	
}
