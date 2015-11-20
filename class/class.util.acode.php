<?php
require_once('class.DBMySQLi.php');

/**
 *@class Classe herdada DBMySQLi
 *@date 29/04/2014
 *@author Daniel Braga
 */
class Boleto extends DBMySQLi{
	
	protected  $hostLocal			= "192.168.0.199";
	protected  $userLocal			= "alo";
	protected  $passLocal			= "32748178807";
	protected  $dataBaseLocal 	 	= "sysfar_adm";
	
	/**
	*valida string XML a partir do XSD
	* @method validateXML
	* @method $xmlString string, $xsdSource string, 
	* @method $resValidate string
	*/
	public function validateXML($xmlString,$xsdSource){
		
		libxml_use_internal_errors(true);
	
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->loadXML($xmlString);
		$doc->schemaValidate($xsdSource);
		
		$errorXML = libxml_get_last_error();
		if(empty($errorXML->message)){
			$resValidate	=  NULL;
		}
		else{
			$resValidate	= $errorXML->message;
		}
		
		return	 $resValidate;
	}
	
	/**
	*valida dados de acesso
	* @method valAcesso
	* @method $client int, $pass int, 
	* @method $retValAcesso boolean
	*/
	public function valAcesso($client,$pass){
		
		$retValAcesso	= false;
		if( !empty($client) && !empty($pass) ){
			try{
				$query			= 'SELECT codigo FROM clientes WHERE codigo = '.$client.' AND sr_deleted <> "T"';
				$resQuery		= $this->execQueryDB($query,$this->hostLocal,$this->userLocal,$this->passLocal, $this->dataBaseLocal);
			}catch(Exception $e){
				return false;
			}
			$checkPass = $client.(strrev($client));
			if(!empty($resQuery) && $checkPass == $pass){
				$retValAcesso	= true;
			}
		}
		return $retValAcesso;
	}
	
	/**
	*valida dados de acesso
	* @method getDataBoleto
	* @method $client int
	* @method $retDataBoleto array
	*/
	public function getDataBoleto($client){
		$retDataBoleto = array();
		
		$urlBoleto		= 'http://ip.sysfar.com.br/boleto/';
		
		if(!empty($client)){
			try{
				$queryDataBoleto	= 'SELECT
											date_format(receber.vencimento,"%d/%m/%Y") AS vencimento, 
											IF( receber.cc = 1 OR receber.cc = 177, receber.valor + COALESCE (clientes.dsc, 0), receber.valor) valor, receber.documento,
											IF ( receber.banco = 104 OR receber.banco = 105, "caixa", "santander") AS banco
										FROM
											receber
										INNER JOIN custos ON receber.cc = custos.codigo
										INNER JOIN clientes ON receber.cliente = clientes.codigo
										WHERE (receber.pago is null AND receber.cliente = '.$client.') 
										AND ( receber.sr_deleted <> "T" AND clientes.sr_deleted <> "T" ) AND receber.documento <> "" AND custos.sr_deleted <> "T"
										ORDER BY receber.vencimento DESC';
				$resDataBoleto		= $this->execQueryDB($queryDataBoleto,$this->hostLocal,$this->userLocal,$this->passLocal, $this->dataBaseLocal);
			}catch(Exception $e){
				return false;
			}
			if(!empty($resDataBoleto)){
				foreach($resDataBoleto as $dataBoleto){
					$arrayDataRet	= array();
					
					$linkBoleto	= $urlBoleto.$dataBoleto['banco'].'.php?doc='.base64_encode($dataBoleto['documento']);
					
					$arrayDataRet['vencimento']	= $dataBoleto['vencimento'];
					$arrayDataRet['valor']		= $dataBoleto['valor'];
					$arrayDataRet['link']		= $linkBoleto;
					
					$retDataBoleto[] = $arrayDataRet;
				}
			}
		}
		return $retDataBoleto;
	}
}

/*
$boleto = new Boleto();
var_dump($boleto->getDataBoleto(1405));
echo $boleto->getDataBoleto(1405);*/
?>