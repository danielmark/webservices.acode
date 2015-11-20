<?php
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
require_once('class.DBMySQLi.php');

/*
 * @Class:Classe herdada classe DBMysqli
 * @Author:Daniel Braga
 * @Date:01/02/2012
 */
class SGT extends DBMySQLi{
	
	var $DB_hostSGT		= "192.168.0.199";
	var $DB_userSGT		= "webservice";
	var $DB_passSGT		= "32748178807";
	var $DB_dbSGT		= "sysfar_sgt";
	
	var $limiteLista	= 1000;
	
	/* @Metodo: Valida estrutura XML a partir do XSD
	 * @Name:validateXML
	 * @Param: string xml, source xsd
	 * @Return: message error ou null em caso de sucesso
	 * @Date:03/02/2012
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
	
	/* @Metodo:Valida dados da credencial
	 * @Name:valCredencial
	 * @Param: string cnpj, senha, codigo regime tributario
	 * @Return: int
	 * @Date:01/02/2012
	 */
	public function valCredencial ($cnpj, $senha, $regimeTrib) {
		
		$idRet = 0;
		
		/*@Info: Valida CNPJ e senha*/
		$queryValCliente	= "SELECT codigo FROM sgt_clientes WHERE cnpj = '".$cnpj."' AND senha = '".md5($senha)."'";
		$resValCliente		= $this->execQueryDB($queryValCliente,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
		
		if(empty($resValCliente)){
			$idRet = 2;
		}
		else{
			$idCliente		= $resValCliente[0]['codigo'];
			/*@Info: Valida situacao*/
			$queryValSit	= "SELECT codigo_situacao FROM sgt_clientes WHERE codigo = '".$idCliente."'";
			$resValSit		= $this->execQueryDB($queryValSit,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
			$sitCliente		= $resValSit[0]['codigo_situacao'];
			
			if($sitCliente	== 2){
				$idRet = 3;
			}
			else{
				/*@Info: Valida regime tributario*/
				$queryValTrib	= "SELECT codigo FROM sgt_regimetributario WHERE codigo = '".$regimeTrib."'";
				$resValTrib		= $this->execQueryDB($queryValTrib,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
				
				if(empty($resValTrib)){
					$idRet = 4;
				}
				else{
					$idRet = 1;
				}
			}
		}
		
		return $idRet;
	}
	
	/* @Metodo: Metodo responsavel por verifica se estado possui dados de icms configurado a partir do codigo UF
	 * @Name:getStatusUFICMS
	 * @Param: int(codigo uf)
	 * @Return: boolean
	 * @Date:17/12/2014
	 */
	public function getStatusUFICMS($idUF){
		$queryDataUFICMS	= 'SELECT codigo FROM sgt_uf WHERE codigo ='.$idUF;
		$dataUFICMS			= $this->execQueryDB($queryDataUFICMS,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT); 
		$statusUFICMS		= empty($dataUFICMS) ? 0 : 1;
		
		return $statusUFICMS;
	}	
	
	/* @Metodo: Procura item pelo codigo de barras
	 * @Name:searchItem
	 * @Param: string(cod. barra)
	 * @Return: boolean
	 * @Date:06/02/2012
	 */
	public function searchItem($codBarra,$cnpjCliente){
		//Armazena dados cliente
		$dataClient		= $this->getDataClient($cnpjCliente);
		$idUF			= $dataClient['codigouf'];
		$idRegimeTrib	= $dataClient['codigoregimetrib'];
		
		$extSQLDataItensTribCli	= NULL;
		
		//Complementa query data itens conforme o regime tributario do cliente
		switch($idRegimeTrib){
			case 1:
				$extSQLDataItensTribCli	= '_sn';
			break;
		}
		
		//Verifica se estado que o cliente se encontra possui dados icms configurado, caso sim procura item se baseando pelo estado
		$queryDataIten	= NULL;
		if($this->getStatusUFICMS($idUF)){
			$queryDataIten	= 'SELECT
									itens.codigo AS idsgt_item
								FROM
									sgt_itens AS itens
								INNER JOIN sgt_ncm AS ncm ON itens.sgt_ncm_codigo = ncm.codigo
								INNER JOIN sgt_cstpis AS cstpis ON ncm.codigo_cstpis_saida'.$extSQLDataItensTribCli.' = cstpis.codigo
								INNER JOIN sgt_cstcofins AS cstcofins ON ncm.codigo_cstcofins_saida'.$extSQLDataItensTribCli.' = cstcofins.codigo
								INNER JOIN ncm_aliquota_uf ON ncm_aliquota_uf.codigo_ncm = ncm.codigo
								INNER JOIN sgt_csticms AS csticms ON ncm_aliquota_uf.codigo_csticms = csticms.codigo
								INNER JOIN sgt_csosn AS csosn ON ncm_aliquota_uf.codigo_csosn = csosn.codigo
								WHERE
									ncm_aliquota_uf.codigo_uf = '.$idUF.' AND itens.codigo_barra = "'.$codBarra.'"';
		}else{
			$queryDataIten	= 'SELECT
									itens.codigo AS idsgt_item
								FROM
									sgt_itens AS itens
								WHERE
									itens.codigo_barra = "'.$codBarra.'"';
		}

		$dataIten		= $this->execQueryDB($queryDataIten,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT); 
		$idSGTItem		= isset($dataIten[0]['idsgt_item']);
		
		return $idSGTItem;
		
	}
	
	public function regNovoItem($CNPJCliente, $descricao, $principioativo, $laboratorio, $barra , $ncm, $aliquotaicms, $cstpis, $cstcofins, $piscofins, $csticms, $csosn){
		
		//Armazena dados cliente
		$dataClient		= $this->getDataClient($CNPJCliente);
		$idUFClient		= $dataClient['codigouf'];
		
		//Verifica se produto já foi cadastrado na lista de notificação pelo codigo de barras, caso sim retorna statusRegNovoItem como true
		$sqlDataNewItem	= 'SELECT codigo FROM sgt_novoitem WHERE barra="'.$barra.'" AND codigo_uf ='.$idUFClient;
		$dataNewItem	= $this->execQueryDB($sqlDataNewItem,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
		
		if(empty($dataNewItem)){
			$descricao				= empty($descricao) ? NULL : utf8_encode(addslashes(substr($descricao,0,50)));
			$principioativo			= empty($principioativo) ? NULL : utf8_encode(addslashes(substr($principioativo,0,50)));
			$laboratorio			= empty($laboratorio) ? NULL : utf8_encode(addslashes(substr($laboratorio,0,40)));
			$ncm					= empty($ncm) ? 0 : preg_replace( '/[^0-9]/', '', $ncm);
			$aliquotaicms			= empty($aliquotaicms) ? 0 : preg_replace( '/[^0-9]/', '', $aliquotaicms);
			$cstpis					= empty($cstpis) ? 0 : preg_replace( '/[^0-9]/', '', $cstpis);
			$cstcofins				= empty($cstcofins) ? 0 : preg_replace( '/[^0-9]/', '', $cstcofins);
			$piscofins				= empty($piscofins) ? 0 : preg_replace( '/[^0-9]/', '', $piscofins);
			$csticms				= empty($csticms) ? 0 : preg_replace( '/[^0-9]/', '', $csticms);
			$csosn					= empty($csosn) ? 0 : preg_replace( '/[^0-9]/', '', $csosn);
			
			//registra notificação
			$queryRegNotice	= 'INSERT INTO sgt_notificacao 
									(codigo_tiponotificacao, cnpj_cliente, descricao_item, data, hora, pendente)
									VALUES
									(1, "'.$CNPJCliente.'", "'.$descricao.'", "'.date("Y-m-d").'", "'.date("H:i:s").'", 1)';
			$this->execQueryDB($queryRegNotice,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
			
			$queryDataNotice 	= 'SELECT MAX(codigo) last_id FROM sgt_notificacao';
			$dataNotice			= $this->execQueryDB($queryDataNotice,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
			
			$sqlRegItem				= 'INSERT INTO sgt_novoitem (
											codigo_uf,
											codigo_notificacao,
											descricao,
											principio_ativo,
											laboratorio,
											barra,
											ncm,
											aliquotaicms,
											cstpis,
											cstcofins,
											piscofins,
											csticms,
											csosn
										)
										VALUES
											(
												'.$idUFClient.',
												'.$dataNotice[0]['last_id'].',
												"'.$descricao.'",
												"'.$principioativo.'",
												"'.$laboratorio.'",
												"'.$barra.'",
												'.$ncm.',
												'.$aliquotaicms.',
												'.$cstpis.',
												'.$cstcofins.',
												'.$piscofins.',
												'.$csticms.',
												'.$csosn.'
											)';	
			
			$resRegItem				= $this->execQueryDB($sqlRegItem,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
			if($resRegItem == 1){
				$statusRegNovoItem = true;}
			else{
				$statusRegNovoItem = false;}
		}else{
			$statusRegNovoItem = true;
		}
		
		return $statusRegNovoItem;
	}
	
	/* @Metodo: Retorna total partes da listagem 
	 * @Name:getTotalPartesLista
	 * @Param: null
	 * @Return: int
	 * @Date:20/04/2012
	 */
	public function getTotalPartesLista(){
		$sqlTotalReg	= "SELECT count(*) AS count FROM sgt_itens";
		$totalReg		= $this->execQueryDB($sqlTotalReg,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
		$totalReg		= $totalReg[0]['count'];
		$totalPartes	= ceil($totalReg / $this->limiteLista);
		
		return $totalPartes;
	}
	
	/* @Metodo: Retorna listagem de dados itens a partir do numero da lista
	 * @Name:getDataItens
	 * @Param: int lista
	 * @Return: array, null
	 * @Date:02/02/2012
	 */
	public function getDataItens($lista,$regimeTributario){
		$itens = NULL;
		$totalPartes	= $this->getTotalPartesLista();

		//Verifica se lista é maior ou igual que 1
		if($lista >= 1){
			
			//Verifica se lista é maior que o total de paginas
			if($lista <= $totalPartes){
				$inicioLista	= ($lista-1) * $this->limiteLista;
				
				if($regimeTributario = 2 || $regimeTributario = 3){
					//Caso o cliente seja lucro presumido ou real
					$queryDataItens	 = 'SELECT
											itens.codigo codigosgt,
											itens.codigo_barra barra,
											ncm.ncm,
											itens.codigo_aliquotaicms aliquotaicms,
											cstpis.codigo_cstpis cstpis,
											cstcofins.codigo_cstcofins cstcofins,
											itens.codigo_piscofins piscofins,
											csticms.codigo_csticms csticms,
											csosn.codigo_csosn csosn,
											itens.codigo_historicoitem
										FROM
											sgt_itens itens
										INNER JOIN sgt_ncm ncm ON itens.codigo_ncm = ncm.codigo
										INNER JOIN sgt_cstpis cstpis ON itens.codigo_cstpis_saida = cstpis.codigo
										INNER JOIN sgt_cstcofins cstcofins ON itens.codigo_cstcofins_saida = cstcofins.codigo
										INNER JOIN sgt_csosn csosn ON itens.codigo_csosn = csosn.codigo
										INNER JOIN sgt_csticms csticms ON itens.codigo_csticms = csticms.codigo';
				}else{
					//Caso o cliente seja lucro presumido ou real
					$queryDataItens	 = 'SELECT
											itens.codigo codigosgt,
											itens.codigo_barra barra,
											ncm.ncm,
											itens.codigo_aliquotaicms aliquotaicms,
											cstpis.codigo_cstpis cstpis,
											cstcofins.codigo_cstcofins cstcofins,
											itens.codigo_piscofins piscofins,
											csticms.codigo_csticms csticms,
											csosn.codigo_csosn csosn,
											itens.codigo_historicoitem
										FROM
											sgt_itens itens
										INNER JOIN sgt_ncm ncm ON itens.codigo_ncm = ncm.codigo
										INNER JOIN sgt_cstpis cstpis ON itens.codigo_cstpis_saida_sn = cstpis.codigo
										INNER JOIN sgt_cstcofins cstcofins ON itens.codigo_cstcofins_saida_sn = cstcofins.codigo
										INNER JOIN sgt_csosn csosn ON itens.codigo_csosn = csosn.codigo
										INNER JOIN sgt_csticms csticms ON itens.codigo_csticms = csticms.codigo';
				}
				
				$dataItens	= $this->execQueryDB($queryDataItens,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
			}
		}
		return	 $dataItens;
	}
	
	/* @Metodo: Retorna historico do item informado
	 * @Name:getHistItem
	 * @Param: int getHistItem
	 * @Return: array
	 * @Date:27/04/2012
	 */
	public function getHistItem($codigoItem,$cnpjCliente){
		
		//Armazena dados cliente
		$dataClient		= $this->getDataClient($cnpjCliente);
		$idUF			= $dataClient['codigouf'];
		
		$tableHistItens	= NULL;
		if($this->getStatusUFICMS($idUF)){
			$tableHistItens = 'sgt_historicoitem_uf'.$idUF;
		}else{
			$tableHistItens = 'sgt_historicoitem';
		}
		
		$query	= 'SELECT descricao, DATE_FORMAT(data,"%d/%m/%Y") as data, hora FROM '.$tableHistItens.' WHERE codigo_item = '.$codigoItem;
		return $this->execQueryDB($query,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
	}
	
	/* @Metodo: Retorna dados item por codigo de barra
	 * @Name:getDataItemBarra
	 * @Param: string codBarra
	 * @Return: array
	 * @Date:20/04/2012
	 */
	public function getDataItemBarra($codBarra, $cnpjCliente){
		//Armazena dados cliente
		$dataClient		= $this->getDataClient($cnpjCliente);
		$idUF			= $dataClient['codigouf'];
		$idRegimeTrib	= $dataClient['codigoregimetrib'];
		
		$extSQLDataItensTribCli	= NULL;
		
		//Complementa query data itens conforme o regime tributario do cliente
		switch($idRegimeTrib){
			case 1:
				$extSQLDataItensTribCli	= '_sn';
			break;
		}
		
		if($this->getStatusUFICMS($idUF)){
			$queryDataIten	= 'SELECT
								dadosItens.idsgt_item AS codigosgt,
								dadosItens.barraItem AS barra,
								dadosItens.ncm,
								dadosItens.piscofins,
								dadosItens.cstpis,
								dadosItens.cstcofins,
								dadosItens.csticms,
								dadosItens.csosn,
								dadosItens.aliquotaicms,
								MAX(historico.codigo) AS historicoitem
							FROM
								(
									SELECT
										itens.codigo AS idsgt_item,
										itens.codigo_barra AS barraItem,
										ncm.ncm,
										ncm.codigo_piscofins AS piscofins,
										cstpis.codigo_cstpis AS cstpis,
										cstcofins.codigo_cstcofins AS cstcofins,
										csticms.codigo_csticms AS csticms,
										csosn.codigo_csosn AS csosn,
										ncm_aliquota_uf.codigo_aliquotaicms AS aliquotaicms
									FROM
										sgt_itens AS itens
									INNER JOIN sgt_ncm AS ncm ON itens.sgt_ncm_codigo = ncm.codigo
									INNER JOIN sgt_cstpis AS cstpis ON ncm.codigo_cstpis_saida'.$extSQLDataItensTribCli.' = cstpis.codigo
									INNER JOIN sgt_cstcofins AS cstcofins ON ncm.codigo_cstcofins_saida'.$extSQLDataItensTribCli.' = cstcofins.codigo
									INNER JOIN ncm_aliquota_uf ON ncm_aliquota_uf.codigo_ncm = ncm.codigo
									INNER JOIN sgt_csticms AS csticms ON ncm_aliquota_uf.codigo_csticms = csticms.codigo
									INNER JOIN sgt_csosn AS csosn ON ncm_aliquota_uf.codigo_csosn = csosn.codigo
									WHERE
										ncm_aliquota_uf.codigo_uf = '.$idUF.' AND itens.codigo_barra = "'.$codBarra.'"
								) dadosItens
							INNER JOIN sgt_historicoitem_uf'.$idUF.' historico ON historico.codigo_item = dadosItens.idsgt_item GROUP BY
														codigosgt';
		}
		else{
			//Dados itens Federal
			$queryDataIten = 'SELECT
								dadosItens.idsgt_item AS codigosgt,
								dadosItens.barraItem AS barra,
								dadosItens.ncm,
								dadosItens.piscofins,
								dadosItens.cstpis,
								dadosItens.cstcofins,
								MAX(historico.codigo) AS historicoitem
							FROM
								(
									SELECT
										itens.codigo AS idsgt_item,
										itens.codigo_barra AS barraItem,
										ncm.ncm,
										ncm.codigo_piscofins AS piscofins,
										cstpis.codigo_cstpis AS cstpis,
										cstcofins.codigo_cstcofins AS cstcofins
									FROM
										sgt_itens AS itens
									INNER JOIN sgt_ncm AS ncm ON itens.sgt_ncm_codigo = ncm.codigo
									INNER JOIN sgt_cstpis AS cstpis ON ncm.codigo_cstpis_saida'.$extSQLDataItensTribCli.' = cstpis.codigo
									INNER JOIN sgt_cstcofins AS cstcofins ON ncm.codigo_cstcofins_saida'.$extSQLDataItensTribCli.' = cstcofins.codigo
									WHERE 
										itens.codigo_barra = "'.$codBarra.'"
								) dadosItens
							INNER JOIN sgt_historicoitem historico ON historico.codigo_item = dadosItens.idsgt_item GROUP BY codigosgt';
		}
		
		return  $this->execQueryDB($queryDataIten,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
	}
	
	/* @Metodo: Retorna listagem de dados itens a partir do historico, false caso a entrada for vazia
	 * @Name:getDataItens
	 * @Param: histUpdate
	 * @Return: array or false
	 * @Date:02/02/2012
	 */
	public function getDataItensUpdate($histUpdate, $cnpjCliente){
		
		//Armazena dados cliente
		$dataClient		= $this->getDataClient($cnpjCliente);
		$idUF			= $dataClient['codigouf'];
		$idRegimeTrib	= $dataClient['codigoregimetrib'];
		
		$extSQLDataItensTribCli	= NULL;
		
		//Complementa query data itens conforme o regime tributario do cliente
		switch($idRegimeTrib){
			case 1:
				$extSQLDataItensTribCli	= '_sn';
			break;
		}
		
		if($this->getStatusUFICMS($idUF)){
			$queryDataItens	= 'SELECT
								dadosItens.idsgt_item AS codigosgt,
								dadosItens.barraItem AS barra,
								dadosItens.ncm,
								dadosItens.piscofins,
								dadosItens.cstpis,
								dadosItens.cstcofins,
								dadosItens.csticms,
								dadosItens.csosn,
								dadosItens.aliquotaicms,
								MAX(historico.codigo) AS historicoitem
							FROM
								(
									SELECT
										itens.codigo AS idsgt_item,
										itens.codigo_barra AS barraItem,
										ncm.ncm,
										ncm.codigo_piscofins AS piscofins,
										cstpis.codigo_cstpis AS cstpis,
										cstcofins.codigo_cstcofins AS cstcofins,
										csticms.codigo_csticms AS csticms,
										csosn.codigo_csosn AS csosn,
										ncm_aliquota_uf.codigo_aliquotaicms AS aliquotaicms
									FROM
										sgt_itens AS itens
									INNER JOIN sgt_ncm AS ncm ON itens.sgt_ncm_codigo = ncm.codigo
									INNER JOIN sgt_cstpis AS cstpis ON ncm.codigo_cstpis_saida'.$extSQLDataItensTribCli.' = cstpis.codigo
									INNER JOIN sgt_cstcofins AS cstcofins ON ncm.codigo_cstcofins_saida'.$extSQLDataItensTribCli.' = cstcofins.codigo
									INNER JOIN ncm_aliquota_uf ON ncm_aliquota_uf.codigo_ncm = ncm.codigo
									INNER JOIN sgt_csticms AS csticms ON ncm_aliquota_uf.codigo_csticms = csticms.codigo
									INNER JOIN sgt_csosn AS csosn ON ncm_aliquota_uf.codigo_csosn = csosn.codigo
									WHERE
										ncm_aliquota_uf.codigo_uf = '.$idUF.'
								) dadosItens
							INNER JOIN sgt_historicoitem_uf'.$idUF.' historico ON historico.codigo_item = dadosItens.idsgt_item WHERE historico.codigo > '.$histUpdate.'
							GROUP BY
								codigosgt 
							ORDER BY 
								historicoitem, codigosgt';
		}else{
			
			$queryDataItens	= 'SELECT
									itens.codigo AS codigosgt,
									itens.codigo_barra AS barra,
									ncm.ncm,
									ncm.codigo_piscofins AS piscofins,
									cstpis.codigo_cstpis AS cstpis,
									cstcofins.codigo_cstcofins AS cstcofins,
									MAX(historico.codigo) AS historicoitem
								FROM
									sgt_itens AS itens
								INNER JOIN sgt_ncm AS ncm ON itens.sgt_ncm_codigo = ncm.codigo
								INNER JOIN sgt_cstpis AS cstpis ON ncm.codigo_cstpis_saida'.$extSQLDataItensTribCli.' = cstpis.codigo
								INNER JOIN sgt_cstcofins AS cstcofins ON ncm.codigo_cstcofins_saida'.$extSQLDataItensTribCli.' = cstcofins.codigo
								INNER JOIN sgt_historicoitem historico ON historico.codigo_item = itens.codigo WHERE historico.codigo > '.$histUpdate.'
								GROUP BY
									codigosgt 
								ORDER BY 
									historicoitem, codigosgt';
		}
		
			
		return $this->execQueryDB($queryDataItens,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
	}
	
	/* @Metodo: Retorno o ultimo historico de atualizacao dos itens
	 * @Name:getHistItens
	 * @Param: NULL
	 * @Return: int
	 * @Date:01/02/2012
	 */
	public function getHistItens($cnpjCliente){
		
		$lastIdHist	= NULL;
		
		//Armazena dados cliente
		$dataClient		= $this->getDataClient($cnpjCliente);
		$idUF			= $dataClient['codigouf'];
		
		if($this->getStatusUFICMS($idUF)){
			$query		= 'SELECT MAX(codigo) codigo FROM sgt_historicoitem_uf'.$idUF;
		}else{
			$query		= 'SELECT MAX(codigo) codigo FROM sgt_historicoitem';
		}
		$resQuery	= $this->execQueryDB($query,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
		
		if(!empty($resQuery)){
			$lastIdHist	= $resQuery[0]['codigo'];
		}
		
		return $lastIdHist;
	}
	
	/* @Metodo: Registra notificação correção item
	 * @Name:regNoticeIten
	 * @Param: int idSGTIten
	 * @Return: int status //1-registro notific. realizado com sucesso, 2- Iten não encontrado, 3-Erro ao registrar notific.
	 * @Date:01/02/2012
	 */
	public function regNotifyItem($CNPJCliente, $idSGTIten, $textNotify){
		$status		= 1;
		$textNotify	= empty($textNotify) ? NULL : utf8_encode(addslashes(substr($textNotify,0,200)));
		
		//Armazena dados item
		$queryDataIten	= 'SELECT descricao FROM sgt_itens WHERE codigo = '.$idSGTIten;
		$dataIten		= $this->execQueryDB($queryDataIten,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
		
		if(!empty($dataIten)){
			//Item encontrado, registra notificação
			$queryRegNotice	= 'INSERT INTO sgt_notificacao 
									(codigo_tiponotificacao, cnpj_cliente, descricao_item, data, hora, pendente)
									VALUES
									(3, "'.$CNPJCliente.'", "'.$dataIten[0]['descricao'].'", "'.date("Y-m-d").'", "'.date("H:i:s").'", 1)';
			$this->execQueryDB($queryRegNotice,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
			//Armazena codigo de notificação registrada
			$queryDataNotice 	= 'SELECT MAX(codigo) AS last_id FROM sgt_notificacao';
			$dataNotice			= $this->execQueryDB($queryDataNotice,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
			
			//registra sgt_notificacao_item
			$queryRegNoticeIten = 'INSERT INTO sgt_revisaoitem (
										codigo_notificacao,
										codigo_item,
										texto_revisao
									)
									VALUES
										(
											'.$dataNotice[0]['last_id'].',
											'.$idSGTIten.',
											"'.$textNotify.'"
										)';
			if(!$this->execQueryDB($queryRegNoticeIten,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT)){
				$status	= 3;
			}
		}else{
			$status	= 2;
		}
		return $status;
	}
	
	/* @Metodo: Registra log de operacao e retorna o numero de registro de log
	 * @Name:regLogOpercao
	 * @Param: string cnpj, string operacao
	 * @Return: int
	 * @Date:01/02/2012
	 */
	public function regLogOpercao($CNPJ, $operacao){
		
		$queryLogOp		= "INSERT INTO sgt_historico_ws (cnpj_cliente, operacao, data, hora) VALUES ('".$CNPJ."', '".$operacao."', '".date('Y-m-d')."', '".date('H:i:s')."')";
		$resLogOp		= $this->execQueryDB($queryLogOp,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
		if($resLogOp == 1){
			$queryLastId	= "SELECT max(codigo) AS codigo FROM sgt_historico_ws";
			$resLastId		= $this->execQueryDB($queryLastId,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
			$resLastId		= $resLastId[0]['codigo'];
			
			$res	= $resLastId;
		}
		else{
			$res	= NULL;
		}
		return $res;
	}
	
	/* @Metodo: Retorna dados do cliente a partir do CNPJ
	 * @Name:getDataClient
	 * @Param: string cnpj
	 * @Return: array data client
	 * @Date:30/10/2014
	 */
	public function getDataClient($CNPJ){
		
		$retDataClient		= false;
		
		$queryDataClient	= 'SELECT
								clientes.codigo AS codigocliente,
								clientes.codigo_sysfar,
								clientes.nome,
								clientes.contato,
								clientes.razao_social,
								clientes.ie,
								clientes.cnpj,
								clientes.endereco,
								clientes.cidade,
								clientes.bairro,
								clientes.email,
								clientes.tel,
								clientes.senha,
								clientes.obs,
								clientes.codigo_regimetributario,
								uf.sigla,
								situacaocliente.descricao AS situacao,
								regimetrib.descricao AS regimetrib,
								situacaocliente.codigo AS codigosituacao,
								uf.codigo AS codigouf,
								regimetrib.codigo AS codigoregimetrib
							FROM
								sgt_clientes AS clientes
							INNER JOIN sgt_estadosbrasileiros AS uf ON clientes.codigo_uf = uf.codigo
							INNER JOIN sgt_situacaocliente AS situacaocliente ON clientes.codigo_situacao = situacaocliente.codigo
							INNER JOIN sgt_regimetributario AS regimetrib ON clientes.codigo_regimetributario = regimetrib.codigo
								WHERE cnpj = '.$CNPJ;
								
		$dataClient			= $this->execQueryDB($queryDataClient,$this->DB_hostSGT,$this->DB_userSGT,$this->DB_passSGT,$this->DB_dbSGT);
		if(!empty($dataClient)){
			foreach($dataClient as $dataClient){
				$retDataClient = $dataClient;
			}
		}
		
		return $retDataClient;
	}
	
	public function encryptStringFile($string){
		$stringEncrypt = $string;
		return bin2hex(strrev($stringEncrypt));
	}
	
	function genFileDataItens($arrayDataItens, $CNPJ){
		
		if(!empty($arrayDataItens)){
			ini_set('memory_limit', '1024M');//Seta o maximo de memoria para 512mb para geração de arquivos maiores
			
			$extDirFile	= '';
			$dirFile	= 'tempFilesWS/';
			$dataClient	= $this->getDataClient($CNPJ);
			
			//Gera nome arquivo
			$extFile	= '.txt';
			$fileName	= 'WSSGT';
			//Armazena codigo sysfar arquivo
			$idClientNameFile	= str_pad($dataClient['codigo_sysfar'], 4, "0", STR_PAD_LEFT);
			//Armazena configuração ICSM estado cliente
			$statusUFICMS	= $this->getStatusUFICMS($dataClient['codigouf']);
			$fileName .= $idClientNameFile.$dataClient['codigo_regimetributario'].$dataClient['sigla'].$statusUFICMS.$extFile;
			
			//Remove arquivo caso existir
			if(file_exists($extDirFile.$dirFile.$fileName)){
				unlink($extDirFile.$dirFile.$fileName);
			}
			//Remove arquivos que com data diferente da atual
			$stringDataFile	= NULL;
			
			foreach($arrayDataItens as $itens){
				if($statusUFICMS){
					$stringDataFile .= $itens['codigosgt'].'|'.$itens['barra'].'|'.$itens['ncm'].'|'.$itens['aliquotaicms'].'|'.(string)$itens['cstpis'].'|'.(string)$itens['cstcofins'].'|'.$itens['piscofins'].'|'.$itens['csticms'].'|'.$itens['csosn'].'|'.$itens['historicoitem'].'#';
				}else{
					$stringDataFile .= $itens['codigosgt'].'|'.$itens['barra'].'|'.$itens['ncm'].'|'.(string)$itens['cstpis'].'|'.(string)$itens['cstcofins'].'|'.$itens['piscofins'].'|'.$itens['historicoitem'].'#';
				}
			}
			
			$file = fopen($extDirFile.$dirFile.$fileName, 'w');
			fwrite($file, $this->encryptStringFile($stringDataFile));
            fclose($file);
			
			return $dirFile.$fileName;
		}else{
			return false;
		}

	}
	
}

/*
$myClass = new SGT();
echo "<pre>";
var_dump($myClass->searchItem("7896006212508","08861435000132"));*/
?>