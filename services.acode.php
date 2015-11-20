<?php
/*
 * @Info:Services SysFar ACODE
 * @Author:Daniel Braga
 * @Date:01/10/2015
 */
require_once('class/nusoap.php');
require_once('class/class.util.acode.php');

$server	= new soap_server;
$server->configureWSDL('services.acode', 'services.acode');
$server->wsdl->schemaTargetNamespace	= 'urn:services.acode';

/*
 * @Info:Registo de função setLogACODE//Metodo setLogACODE
 * @Params:XML formato string
 * @Return:XML formato string
 * @Author:Daniel Braga
 * @Date:01/10/2015
 */
$server->register('setLogACODE',
array('XML' => 'xsd:string'),
array('return' => 'xsd:string'),
'urn:services.boleto',
'urn:services.acode#setLogACODE',
'rpc',
'encoded',
'Registra log envio ACODE, XSD: <a href="http://ip.sysfar.com.br/webservice/xsd/acode_setlogacode.xsd">Documentacao XSD</a>');

function setLogACODE($XML){
	$utilACODE	= new utilACODE();
	
	$xmlDocRet	= "";
	$resValXML	= $utilACODE->validateXML($XML,"xsd/acode_setlogacode.xsd");
	
	if(empty($resValXML)){
		$XML	= simplexml_load_string($XML);
		
		$credentials	= rtrim(trim(addslashes(strval($XML->credencial))));
        $date       = intval($XML->data);
        $idClient   = intval($XML->loja);
        $status     = intval($XML->status);

        //Valida Credencial
		if($credentials == md5($idClient)){

            //Registra log
            $resSetDataLog = $utilACODE->setDataLog($idClient);

            if($resSetDataLog['status']){
                $xmlDocRet = "<?xml version='1.0' encoding='utf-8' ?>\r";
                $xmlDocRet .= "\t<retSetLogACODE>\r";
                $xmlDocRet .= "\t\t<codigo>0</codigo>\r";
                $xmlDocRet .= "\t\t<msg>Success</msg>\r";
                $xmlDocRet .= "\t</retSetLogACODE>\r";
            }else{
                $xmlDocRet = "<?xml version='1.0' encoding='utf-8' ?>\r";
                $xmlDocRet .= "\t<retSetLogACODE>\r";
                $xmlDocRet .= "\t\t<codigo></codigo>\r";
                $xmlDocRet .= "\t\t<msg>Erro inesperado: ".$resSetDataLog['errorInfo']."</msg>\r";
                $xmlDocRet .= "\t</retSetLogACODE>\r";
            }
		}
		else{
			//Credencial invalida
			$xmlDocRet = "<?xml version='1.0' encoding='utf-8' ?>\r";
			$xmlDocRet .= "\t<retSetLogACODE>\r";
				$xmlDocRet .= "\t\t<codigo>1</codigo>\r";
				$xmlDocRet .= "\t\t<msg>Credencial invalida</msg>\r";
			$xmlDocRet .= "\t</getDataBoletoRet>\r";
		}
	}else{
		//Erro estrutura XML
		$xmlDocRet = "<?xml version='1.0' encoding='utf-8' ?>\r";
		$xmlDocRet .= "\t<retSetLogACODE>\r";
			$xmlDocRet .= "\t\t<codigo>2</codigo>\r";
			$xmlDocRet .= "\t\t<msg>Erro estrutura XML:".$resValXML."</msg>\r";
		$xmlDocRet .= "\t</retSetLogACODE>\r";}
	
	return $xmlDocRet;
}

$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
$server->service($HTTP_RAW_POST_DATA);
?>