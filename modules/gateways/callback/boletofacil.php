<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

if ($_POST) {
		
	// Carrega os dados do modulo
	$gatewayModuleName = basename(__FILE__, '.php');
	$gatewayParams = getGatewayVariables($gatewayModuleName);

	// Die if module is not active.
	if (!$gatewayParams['type']) {
	    die("Module Not Activated");
	}
		
	$v_token 		= $_POST["paymentToken"];
	$v_invoice 		= $_POST["chargeReference"];
	$v_codigo		= $_POST["chargeCode"];

	$invoice		= $_GET["invoiceid"];
	$secret			= $_GET["secret"];
	
	if (is_numeric($invoice) && $invoice > 0 && $v_invoice == $invoice) {

		$invoiceId = checkCbInvoiceID($invoice, $gatewayParams['name']);

		if ($secret == md5($invoiceId . $gatewayParams["secret"])) {
			$transactionStatus = 'Success';
			$success = true;
		} else {
			$transactionStatus = 'Hash Verification Failure';
			$success = false;
		}
			
		checkCbTransID($v_codigo);
		
		logTransaction($gatewayParams['name'], $_POST, $transactionStatus);
		
		if ($success) {
			$servidor = ($gatewayParams['testmode'] == "on") ? "https://sandbox.boletobancario.com/boletofacil/integration/api/v1/fetch-payment-details" : "https://www.boletobancario.com/boletofacil/integration/api/v1/fetch-payment-details";

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $servidor);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "paymentToken=" . $v_token);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$resp = curl_exec($ch);
			curl_close($ch);			
	
			$dados = json_decode($resp);
			
			if (!$dados->success) {
				logTransaction($gatewayParams['name'], $dados->errorMessage, "Error");
				return "Erro ao processar boleto: " .  $dados->errorMessage;
			} else {
				logTransaction($gatewayParams['name'], $_POST, $transactionStatus);
				
				if ($gatewayParams["tarifa"] > 0) {
					$valor = $dados->data->payment->amount - $gatewayParams["tarifa"];
				} else {
					$valor = $dados->data->payment->amount;
				}
			    addInvoicePayment(
			        $invoiceId,
			        $v_codigo,
			        $valor,
			        $dados->data->payment->fee,
			        $gatewayModuleName
				);
				echo "OK";
			}
		} else {
			echo "ERRO";
		}
	} else {
		echo "ERRO";
	}	
} else {
	echo "ERRO2";
}
?>
