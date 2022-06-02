<?php
define('MODX_API_MODE', true);
require dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';

$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');

$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Получение данных
    $json = $_POST['Data'];
    $signature = $_POST['Signature'];

    // Преобразуем из JSON в Array
    $data = json_decode($json, true);

    $id = $data['AccountNo'];
	
    if (is_numeric($id))
    {
       $order = $modx->getObject('msOrder', array('id'=>$id));
    } 
    else
    {
       $order = false;
    }

    if ($modx->getOption('EXPRESS_PAY_IS_USE_SIGNATURE_FROM_NOTIFICATION_EPOS') && $order) {

        $secretWord = $modx->getOption('EXPRESS_PAY_SECRET_WORD_FROM_NOTIFICATION_EPOS');

        if ($signature == computeSignature($json, $secretWord)) {
            if ($data['CmdType'] == '3' && $data['Status'] == '3') {
                $miniShop2->changeOrderStatus($order->id, 2); // Изменение статуса заказа на оплачен
                header("HTTP/1.0 200 OK");
                print $status = 'OK | payment received'; //Все успешно
            }elseif ($data['CmdType'] == '3' && $data['Status'] == '5')
            {
                $miniShop2->changeOrderStatus($order->id, 4); // Изменение статуса заказа на отменён
                header("HTTP/1.0 200 OK");
                print $status = 'OK | payment received'; //Все успешно
            }
        } else {
            header("HTTP/1.0 400 Bad Request");
            print $status = 'FAILED | wrong notify signature  ' .print_r($json,1). computeSignature($json, $secretWord). $secretWord; //Ошибка в параметрах
        }
    } elseif ($order) {
        if ($data['CmdType'] == '3' && $data['Status'] == '3') 
        {
            $miniShop2->changeOrderStatus($order->id, 2); // Изменение статуса заказа на оплачен
            header("HTTP/1.0 200 OK");
            print $status = 'OK | payment received'; //Все успешно
        }elseif ($data['CmdType'] == '3' && $data['Status'] == '5')
        {
            $miniShop2->changeOrderStatus($order->id, 4); // Изменение статуса заказа на отменён
            header("HTTP/1.0 200 OK");
            print $status = 'OK | payment received'; //Все успешно
        }
    } else {
        header("HTTP/1.0 200 Bad Request");
        print $status = 'FAILED | ID заказа неизвестен';
    }
}

// Проверка электронной подписи
function computeSignature($json, $secretWord)
{
    $hash = NULL;

    $secretWord = trim($secretWord);

    if (empty($secretWord))
        $hash = strtoupper(hash_hmac('sha1', $json, ""));
    else
        $hash = strtoupper(hash_hmac('sha1', $json, $secretWord));
    return $hash;
}
