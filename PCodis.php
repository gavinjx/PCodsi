<?php 
require 'ZookeeperClient.php' ;
class PCodis 
{
	private static $_zkConnector ;
	private static $_proxyName ;
	private static $_proxyNum ;

	private static $_codisInstance ;

	/**
	 * @param [string] $address [e.g. "host1:2181,host2:2181"]
	 * @return [Object ZookeeperClient]
	 */
	private function _getZkConnector($address)
	{
		if(self::$_zkConnector !== null ){
			return self::$_zkConnector ;
		}else{
			return self::$_zkConnector = new ZookeeperClient($address) ;
		}
	}



	private static function _setProxyName($proxyName){
		self::$_proxyName = $proxyName ;
	}

	public static function getProxyName(){
		return self::$_proxyName ;
	}

	private static function _setProxyNum($proxyNum){
		self::$_proxyNum = $proxyNum ;
	}

	public static function getProxyNum(){
		return self::$_proxyNum ;
	}

	public static function getCodisInstance($address, $proxyPath, $retryTime=1){

		if(!self::$_codisInstance){

			$redis = new Redis() ;

			//until get a avalible proxy node
			$proxyNum = 0 ;
			do{
				$proxy = self::selectProxy($address, $proxyPath) ;
				$proxyNum++ ;

				$addr = explode(':', $proxy) ;
				$connector = $redis->connect($addr[0], $addr[1]) ;
				if(!$connector){
					$i = 0 ;
					//retry
					while($i < $retryTime){
						$connector = $redis->connect($addr[0], $addr[1]) ;
						$i++ ;
						if($connector){
							self::$_codisInstance = $redis ;
							break ;
						}
					}

					//upto retry maxtime,delete the proxy and then get a new proxy
					if($i == $retryTime){
						//delete
						self::deleteProxy($address, $proxyPath, self::getProxyName()) ;
					}

				}else{
					self::$_codisInstance = $redis ;
					break ;
				}

			}while(!self::$_codisInstance && $proxyNum<=self::getProxyNum()) ;
		}

		return self::$_codisInstance ;

	}
	/**
	 * Get One CodisProxy Address
	 * @param  [string] $address   [description]
	 * @param  [string] $proxyPath [description]
	 * @return [string]            [CodisProxyAddr, e.g. "127.0.0.1:19000"]
	 */
	public static function selectProxy($address, $proxyPath)
	{
		if(substr($proxyPath, -1) == '/'){ //if the last char is "/" then delete it
			$proxyPath = substr($proxyPath, 0, -1) ;
		}
		$proxyNodes = self::_getZkConnector($address)->getChildren($proxyPath) ;

		if(is_array($proxyNodes)){
			$proxyNum = count($proxyNodes) ;
			$proxyName = $proxyNodes[rand(0, $proxyNum-1)] ;
			$proxyStr = self::_getZkConnector($address)->get($proxyPath.'/'.$proxyName) ;
			if(strlen($proxyStr)>0 && $proxyInfo = json_decode($proxyStr, true)){
				self::_setProxyName($proxyName) ;
				self::_setProxyNum($proxyNum) ;
				return $proxyInfo['addr'] ;
			}
		}
		return '' ;
		
	}

	/**
	 * Remove the Node
	 * @param  [string] $address   [description]
	 * @param  [string] $proxyPath [description]
	 * @param  [string] $proxyName [description]
	 * @return [type]            [description]
	 */
	public static function deleteProxy($address, $proxyPath, $proxyName)
	{
		if(substr($proxyPath, -1) == '/'){ //if the last char is "/" then delete it
			$proxyPath = substr($proxyPath, 0, -1) ;
		}
		return self::_getZkConnector($address)->removeNode($proxyPath.'/'.$proxyName) ;
	}

}
