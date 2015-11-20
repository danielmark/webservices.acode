<?php
/*
 * @Class:Classe herdada superclasse MySQLi
 * @Author:Daniel Braga
 * @Date:20/09/2011
 */
class DBMySQLi extends Mysqli
{
	
	/* @Function:Executa comandos sql a partir dos dados de conexao fornecidos
	 * @Name:execQuerySever
	 * @Param: query,host,user,pass,nome banco
	 * @Return: array,integer
	 * @Date:20/09/11
	 */
	 public function execQueryDB($query,$hostServer,$userServer,$passSever,$dbServer){
		
	   	$this->connect($hostServer,$userServer,$passSever,$dbServer);
	   	if(mysqli_connect_errno()) {
		  throw new Exception('Connection Exception: '.mysqli_connect_error());}
		//$this->set_charset("utf8");
		
		$command		= substr($query,0,6);
		if($command == "INSERT" || $command == "DELETE" || $command == "UPDATE"){
			$result = parent::query($query);
			if(!$result){
				throw new Exception('Query Exception: '.mysqli_error($this).' numero:'.mysqli_errno($this).'Query: '.$query);}
			else{
				return $this->affected_rows;}
		}
		if($command == "SELECT"){
			$resultData = NULL;
			$result = parent::query($query);
			if(!$result){
				throw new Exception('Query Exception: '.mysqli_error($this).' numero:'.mysqli_errno($this).'Query: '.$query);}
			else{
				for($x=1;$x <= $result->num_rows;$x++){
					$resultData[] = $result->fetch_array();}
			}
			return $resultData;
		}
	}
	
	public function __destruct(){
		parent::close();}
}
?>