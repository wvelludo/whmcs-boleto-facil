<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function boletofacil_MetaData() {
    return array(
        'DisplayName' => 'Boletobancario.com - Boleto Facil',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function boletofacil_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Boletobancario.com - Boleto Facil"),
     "token" => array("FriendlyName" => "Token", "Type" => "text", "Size" => "50", ),
     "secret" => array("FriendlyName" => "Secret", "Type" => "text", "Size" => "50", "Description" => "Usado para comparação no retorno do pagamento (Preferecialmente 32 digitos)"),
     "maxOverdueDays" => array("FriendlyName" => "Após vencimento", "Type" => "text", "Size" => "4", "Description" => "Número máximo de dias que o boleto poderá ser pago após o vencimento (\"0 Desativa\")"),
     "fine" => array("FriendlyName" => "Multa", "Type" => "text", "Size" => "4", "Description" => "Multa para pagamento após o vencimento (Decimal, separado por ponto. Maior ou igual a 0.00 e menor ou igual a 2.00 (máximo permitido por lei)" ),
     "interest" => array("FriendlyName" => "Juros", "Type" => "text", "Size" => "4", "Description" => "Juros para pagamento após o vencimento. Decimal, separado por ponto. Maior ou igual a 0.00 e menor ou igual a 1.00 (máximo permitido por lei)"),
     "tarifa" => array("FriendlyName" => "Tarifa", "Type" => "text", "Size" => "4", "Description" => "Valor adicional para pagamento com boleto. Ex: 1.50"),
     "campocpf" => array("FriendlyName" => "Campo CPF/CNPJ", "Type" => "text", "Size" => "20", "Description" => "Campo customizado que contem o cpf/cnpj. Exemplo: customfields2"),
	 "prorrogavencimento" => array("FriendlyName" => "Prorroga Vencimento", "Type" => "yesno", "Description" => "Se no primeiro acesso a invoice a mesma já estiver vencida gera o boleto com vencimento em 2 dias."),
     "testmode" => array("FriendlyName" => "Ambiente de Testes", "Type" => "yesno", ),
    );
	return $configarray;
}

function boletofacil_link($params) {
		
	// cria tabela de controle caso ela nao exista
	full_query("CREATE TABLE IF NOT EXISTS `mod_boletofacil` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `invoice` int(11) DEFAULT NULL, `code` int(11) DEFAULT NULL, `link` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `IX_INVOICE` (`invoice`)) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;");

	// Verifica se ja existe o boleto
	$resultado = full_query("SELECT * FROM `mod_boletofacil` WHERE invoice = " . $params["invoiceid"]);
	$dados = mysql_fetch_array($resultado);
	if ($dados["invoice"] == $params["invoiceid"] && strlen($dados["code"]) > 1) {
		$code = '<input type="button" value="'.$params['langpaynow'].'" onClick="window.location=\'' . $dados["link"] . '\'" />';
		return $code;
	} else {
		
		// Carrega vencimento da invoice:
		$result = select_query("tblinvoices","",array("id"=>(int)$params["invoiceid"]));
		$data = mysql_fetch_array($result);
		$id = $data["id"];
		$userid = $data["userid"];
		$date = $data["date"];
		$duedate = $data["duedate"];
		$total = $data["total"];
		
		if ($params["prorrogavencimento"] == "on" && $duedate < date("Y-m-d")) {
			$duedate = date("Y-m-d",strtotime('+2 day'));
		}
		
		if ($params["tarifa"] > 0) {
			$amount = $total + $params["tarifa"];
		} else {
			$amount = $total;
		}
		$doc 			= $params["campocpf"];
		$servidor 		= ($params['testmode'] == "on") ? "https://sandbox.boletobancario.com/boletofacil/integration/api/v1/issue-charge" : "https://www.boletobancario.com/boletofacil/integration/api/v1/issue-charge";
		$url_notificacao	= rawurlencode($params["systemurl"] . "/modules/gateways/callback/" . $params["paymentmethod"] . ".php?invoiceid=" . $params["invoiceid"] . "&secret=" .  md5($params["invoiceid"] . $params["secret"]));
		$url 			= $servidor . "?token=" . $params["token"] . "&dueDate=" . date("d/m/Y",strtotime($duedate)) . "&payerEmail=" . rawurlencode($params['clientdetails']['email']) . "&payerPhone=" . rawurlencode($params['clientdetails']['phonenumber']) . "&notifyPayer=false&reference=" . $params["invoiceid"] . "&amount=" . $amount . "&maxOverdueDays=" . $params["maxOverdueDays"] . "&fine=" . $params["fine"] . "&interest=" . $params["interest"] . "&description=" . rawurlencode($params["description"]) . "&payerName=" . rawurlencode($params['clientdetails']['fullname']) . "&notificationUrl=" . $url_notificacao . "&payerCpfCnpj=" . rawurlencode($params['clientdetails'][$doc]);
			
		$curl = curl_init();
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => $url,
		    CURLOPT_USERAGENT => 'PHP Whmcs',
		    CURLOPT_SSL_VERIFYPEER => false
		));
		$resp = curl_exec($curl);
		curl_close($curl);
		
		$dados = json_decode($resp);
		if (!$dados->success) {
			return "Erro ao gerar boleto: " .  $dados->errorMessage;
		} else {	
			full_query("INSERT INTO `mod_boletofacil` (invoice, code, link) VALUES (" . $params["invoiceid"] . ", " . $dados->data->charges[0]->code . ", '" . $dados->data->charges[0]->link . "')");
			$code = '<input type="button" value="'.$params['langpaynow'].'" onClick="window.location=\'' . $dados->data->charges[0]->link . '\'" />';
			return $code;
		}
	}
}
?>
