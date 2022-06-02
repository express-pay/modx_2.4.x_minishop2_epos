<?php

if (!class_exists('msPaymentInterface')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/mspaymenthandler.class.php';
}

class Epos extends msPaymentHandler implements msPaymentInterface
{
    public $modx;

    const CURRENCY = 933;
    const RETURN_TYPE = 'redirect';

    function __construct(xPDOObject $object, $config = array())
    {
        $this->modx = &$object->xpdo;
    }

    //Метод принятия заказа
    public function send(msOrder $order)
    {

        $miniShop2 = $this->modx->getService('minishop2');

        $miniShop2->loadCustomClasses('payment');

        $id = $order->get('id');

        $id_resource = $this->modx->getOption('EXPRESS_PAY_RESOURCE_ID');

        $baseUrl = "https://api.express-pay.by/v1/";

        if ($this->modx->getOption('EXPRESS_PAY_TEST_MODE'))
            $baseUrl = "https://sandbox-api.express-pay.by/v1/";

        $url = $baseUrl . "web_invoices";

        $request_params = $this->getInvoiceParam($order);

        $this->log_info('Response', print_r($request_params, 1));

        $response = $this->sendRequestPOST($url, $request_params);

        $response = json_decode($response, true);

        $this->log_info('Response', print_r($response, 1));

        $home_url =$this->modx->getOption('site_url');

        if (isset($response['Errors'])) {
            $output_error =
                '<br />
            <h3>Ваш номер заказа: ##ORDER_ID##</h3>
            <p>При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина</p>
            <input type="button" value="Продолжить" onClick=\'location.href="##HOME_URL##"\'>';

            $output_error = str_replace('##ORDER_ID##', $id,  $output_error);

            $output_error = str_replace('##HOME_URL##', $home_url,  $output_error);

            $res = $this->modx->getObject('modResource', $id_resource);

            $res->setContent($output_error);

            $res->save();

            //Очистка кеша ресурса
            $key = $res->getCacheKey();
            $cache = $this->modx->cacheManager->getCacheProvider($this->modx->getOption('cache_resource_key', null, 'resource'));
            $cache->delete($key, array('deleteTop' => true));
            $cache->delete($key);

            $url = $this->modx->makeUrl($id_resource);

            $miniShop2->changeOrderStatus($id, 4);

            return $this->success('', array('redirect' => $url));
        } else {
            $output =
                '<table style="width: 100%;text-align: left;">
            <tbody>
                    <tr>
                        <td valign="top" style="text-align:left;">
                        <h3>Ваш номер заказа: ##ORDER_ID##</h3>
                            Вам необходимо произвести платеж в любой системе, позволяющей проводить оплату через ЕРИП (пункты банковского обслуживания, банкоматы, платежные терминалы, системы интернет-банкинга, клиент-банкинга и т.п.).
                            <br />
                            <br />1. Для этого в перечне услуг ЕРИП перейдите в раздел:  <b>Сервис E-POS / E-POS → оплата товаров и услуг</b> <br />
                            <br />2. В поле <b>«Номер кода»</b>введите <b>##ORDER_ID##</b> и нажмите "Продолжить" <br />
                            <br />3. Укажите сумму для оплаты <b>##AMOUNT##</b><br />
                            <br />4. Совершить платеж.<br />
                        </td>
                            <td style="text-align: center;padding: 70px 20px 0 0;vertical-align: middle">
								##OR_CODE##
								<p><b>##OR_CODE_DESCRIPTION##</b></p>
								</td>
						</tr>
				</tbody>
            </table>
            <br />
            <input type="button" value="Продолжить" onClick=\'location.href="##HOME_URL##"\'>';

            $serviceProviderEposCode = $this->modx->getOption('EXPRESS_PAY_SERVICE_PROVIDER_EPOS_CODE');
            $serviceEposCode = $this->modx->getOption('EXPRESS_PAY_SERVICE_EPOS_CODE');

            $output = str_replace('##ORDER_ID##', "$serviceProviderEposCode-$serviceEposCode-$id",  $output);
            $output = str_replace('##AMOUNT##', $request_params['Amount'],  $output);
            $output = str_replace('##HOME_URL##', $home_url,  $output);

            if ($this->modx->getOption('EXPRESS_SHOW_QR_CODE')) {
                $qr_code = $this->getQrCode($response['ExpressPayInvoiceNo']);
                $output = str_replace('##OR_CODE##', '<img src="data:image/jpeg;base64,' . $qr_code . '"  width="200" height="200"/>',  $output);
                $output = str_replace('##OR_CODE_DESCRIPTION##', 'Отсканируйте QR-код для оплаты',  $output);
            } else {
                $output = str_replace('##OR_CODE##', '',  $output);
                $output = str_replace('##OR_CODE_DESCRIPTION##', '',  $output);
            }

            $res = $this->modx->getObject('modResource', $id_resource);

            $res->setContent($output);

            $res->save();

            //Очистка кеша ресурса
            $key = $res->getCacheKey();
            $cache = $this->modx->cacheManager->getCacheProvider($this->modx->getOption('cache_resource_key', null, 'resource'));
            $cache->delete($key, array('deleteTop' => true));
            $cache->delete($key);

            $url = $this->modx->makeUrl($id_resource);

            return $this->success('', array('redirect' => $url));
        }
    }

    // Отправка POST запроса
    public function sendRequestPOST($url, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    // Отправка GET запроса
    public function sendRequestGET($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    //Получение Qr-кода
    public function getQrCode($ExpressPayInvoiceNo)
    {
        $request_params_for_qr = array(
            "Token" => $this->modx->getOption('EXPRESS_PAY_TOKEN_EPOS'),
            "InvoiceId" => $ExpressPayInvoiceNo,
            'ViewType' => 'base64'
        );
        $request_params_for_qr["Signature"] = $this->compute_signature($request_params_for_qr, $this->modx->getOption('EXPRESS_PAY_SECRET_WORD_EPOS'), 'get_qr_code');

        $request_params_for_qr  = http_build_query($request_params_for_qr);
        $response_qr = $this->sendRequestGET('https://api.express-pay.by/v1/qrcode/getqrcode/?' . $request_params_for_qr);
        $response_qr = json_decode($response_qr);
        $qr_code = $response_qr->QrCodeBody;
        return $qr_code;
    }

    //Получение данных для JSON
    public function getInvoiceParam(msOrder $order)
    {
        $id = $order->get('id');
        $adress = $order->getOne('Address');
        $amount = number_format($order->get('cost'), 2, ',', '');
        $info = 'Оплата заказа номер ' . $id . ' в интернет-магазине ' . $this->modx->getOption('site_name');

        $request = array(
            "ServiceId"          => $this->modx->getOption('EXPRESS_PAY_SERVICE_ID_EPOS'),
            "AccountNo"          => $id,
            "Amount"             => $amount,
            "Currency"           => self::CURRENCY,
            'ReturnType'         => "json",
            'ReturnUrl'          => 'nothing',
            'FailUrl'            => 'nothing',
            'Expiration'         => '',
            "Info"               => $info,
            "Surname"            => '',
            "FirstName"          => $adress->get('receiver'),
            "Patronymic"         => '',
            "City"               => $adress->get('city'),
            "Street"             => $adress->get('street'),
            "House"              => $adress->get('building'),
            "Building"           => '',
            "Apartment"          => $adress->get('room'),
            "IsNameEditable"     => $this->modx->getOption('EXPRESS_PAY_IS_NAME_EDITABLE'),
            "IsAddressEditable"  => $this->modx->getOption('EXPRESS_PAY_IS_ADRESS_EDITABLE'),
            "IsAmountEditable"   => $this->modx->getOption('EXPRESS_PAY_IS_AMOUNT_EDITABLE'),
            "EmailNotification"  => $adress->get('email'),
            "SmsPhone"           => preg_replace('/[^0-9]/', '', $adress->get('phone')),
        );

        $request['Signature'] = $this->compute_signature($request, $this->modx->getOption('EXPRESS_PAY_SECRET_WORD_EPOS'));

        return $request;
    }

    //Вычисление цифровой подписи
    public function compute_signature($request_params, $secret_word, $method = 'add_invoice')
    {
        $secret_word = trim($secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
        $api_method = array(
            'add_invoice' => array(
                "serviceid",
                "accountno",
                "amount",
                "currency",
                "expiration",
                "info",
                "surname",
                "firstname",
                "patronymic",
                "city",
                "street",
                "house",
                "building",
                "apartment",
                "isnameeditable",
                "isaddresseditable",
                "isamounteditable",
                "emailnotification",
                "smsphone",
                "returntype",
                "returnurl",
                "failurl"
            ),
            'get_qr_code' => array(
                "invoiceid",
                "viewtype",
                "imagewidth",
                "imageheight"
            ),
            'add_invoice_return' => array(
                "accountno",
                "invoiceno"
            )
        );

        $result = $this->modx->getOption('EXPRESS_PAY_TOKEN_EPOS');

        foreach ($api_method[$method] as $item)
            $result .= (isset($normalized_params[$item])) ? $normalized_params[$item] : '';

        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

        return $hash;
    }


    private function log_info($name, $message)
    {
        $this->log($name, "INFO", $message);
    }

    private function log($name, $type, $message)
    {
        $log_url = dirname(__FILE__) . '/log';

        if (!file_exists($log_url)) {
            $is_created = mkdir($log_url, 0777);

            if (!$is_created)
                return;
        }

        $log_url .= '/express-pay-' . date('Y.m.d') . '.log';

        file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; DATETIME - " . date("Y-m-d H:i:s") . "; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
    }
}
