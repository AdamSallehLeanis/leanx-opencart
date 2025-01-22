<?php

const ORDER_STATUS_PENDING = 1;
const ORDER_STATUS_COMPLETE = 5;

class ControllerExtensionPaymentLeanx extends Controller
{
        public function index($setting)
        {
                $this->load->language('extension/payment/leanx');

                $data['heading_title'] = $this->language->get('heading_title');
                $data['action'] = $this->url->link('extension/payment/leanx/confirm', '', true);

                return $this->load->view('extension/payment/leanx', $data);
        }

        public function confirm()
        {
                $this->load->model('extension/payment/leanx');

                $collection_uuid = $this->config->get('payment_leanx_collection_uuid');
                $is_sandbox = $this->config->get('payment_leanx_is_sandbox');
                $auth_token = $this->config->get('payment_leanx_auth_token');

                // $response = $leanx_api->validateAuthToken();
                // exit(print_r($response));

                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
                $order_id = $order_info['order_id'];
                $invoice_no = $this->guidv4() . '-' . $collection_uuid;

                $parameter = array(
                        'collection_uuid' => trim($collection_uuid),
                        'callback_url' => $this->url->link('extension/payment/leanx/callback', '', true),
                        'redirect_url' => $this->url->link("extension/payment/leanx/redirect_url", array(
                                'invoice_no' => $invoice_no
                        ), true),
                        'amount' => $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false),
                        'full_name' => trim($order_info['firstname'] . ' ' . $order_info['lastname']),
                        'email' => trim($order_info['email']),
                        'phone_number' => trim($order_info['telephone']),
                );

                $leanx_api = new LeanxApi($auth_token, $is_sandbox);
                list($rheader, $rbody) = $leanx_api->paymentPortal($invoice_no, $parameter);
                $this->model_extension_payment_leanx->logger('payment param: ' . json_encode($parameter));
                $this->model_extension_payment_leanx->logger('invoice_no: ' . $invoice_no);
                $this->model_extension_payment_leanx->logger('auth_token: ' . $auth_token);

                if ($rheader !== 200 || $rbody['response_code'] != 2000) {
                        exit(print_r($rbody, true));
                }

                if (!$this->model_extension_payment_leanx->insertBill($order_info['order_id'], $invoice_no)) {
                        $this->model_extension_payment_leanx->logger('Unexpected error. Duplicate Invoice No.');
                        exit('Unexpected error. Duplicate Invoice No.');
                }

                $this->model_checkout_order->addOrderHistory($order_id, ORDER_STATUS_PENDING, "Status Pending. Invoice No: $invoice_no");

                $this->cart->clear();

                header('Location: ' . $rbody['data']['redirect_url']);
        }

        public function callback()
        {
                $this->load->model('extension/payment/leanx');
                $callback_body = json_decode(file_get_contents('php://input'), true);
                $this->model_extension_payment_leanx->logger('Callback body: ' . $callback_body);
                if ($callback_body['response_code'] != 2100 && $callback_body['response_code'] != 2000) {
                        exit(print_r($callback_body));
                }

                $auth_token = $this->config->get('payment_leanx_auth_token');
                $is_sandbox = $this->config->get('payment_leanx_is_sandbox');
                $hash_key = $this->config->get('payment_leanx_hash_key');
                $leanx_api = new LeanxApi($auth_token, $is_sandbox);

                list($rheader, $rbody) = $leanx_api->decode($callback_body['data'], $hash_key);
                if ($rbody['response_code'] != 2100 && $rbody['response_code'] != 2000) {
                        $this->model_extension_payment_leanx->logger('Error decoding');
                        $this->model_extension_payment_leanx->logger($callback_body['data']);
                        exit(print_r($rbody . $callback_body, true));
                }
                $this->model_extension_payment_leanx->logger('Success decode');

                $invoice_status_id = $rbody['data']['invoice_status_id'];
                if ($invoice_status_id != 2) {
                        $this->model_extension_payment_leanx->logger('Invoice not success');
                        exit(print_r($rbody, true));
                }

                $invoice_no = $rbody['data']['client_data']['merchant_invoice_no'];
                $bill_info = $this->model_extension_payment_leanx->getBill($invoice_no);
                $order_id = $bill_info['order_id'];

                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($order_id);

                if ($order_info['order_status_id'] == ORDER_STATUS_PENDING && !$bill_info['paid']) {
                        if ($this->model_extension_payment_leanx->markBillPaid($order_id, $invoice_no)) {
                                $this->model_checkout_order->addOrderHistory($order_id, ORDER_STATUS_COMPLETE, "Status: Paid. Invoice No: $invoice_no. Method: Callback ", true, true);
                        }
                }

                exit('callback success');
        }

        public function redirect_url()
        {
                $this->load->model('extension/payment/leanx');

                $invoice_no = $_GET['amp;invoice_no'];
                if (!$invoice_no) {
                        $this->model_extension_payment_leanx->logger('Missing Invoice No');
                        exit(print_r('Missing Invoice No'));
                }

                $auth_token = $this->config->get('payment_leanx_auth_token');
                $is_sandbox = $this->config->get('payment_leanx_is_sandbox');
                $leanx_api = new LeanxApi($auth_token, $is_sandbox);

                list($rheader, $rbody) = $leanx_api->manualValidateInvoice($invoice_no);
                if ($rbody['response_code'] != 2100 && $rbody['response_code'] != 2000) {
                        $this->model_extension_payment_leanx->logger('Error Invoice No Validation');
                        exit(print_r($rbody, true));
                }

                $transaction_details = $rbody['data']['transaction_details'];
                if ($transaction_details['invoice_status'] != 'SUCCESS') {
                        $this->model_extension_payment_leanx->logger('Invoice not success');
                        $this->rdr($this->url->link('checkout/failure'));
                }

                $bill_info = $this->model_extension_payment_leanx->getBill($invoice_no);
                $order_id = $bill_info['order_id'];

                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($order_id);

                if ($order_info['order_status_id'] == ORDER_STATUS_PENDING && !$bill_info['paid']) {
                        if ($this->model_extension_payment_leanx->markBillPaid($order_id, $invoice_no)) {
                                $this->model_checkout_order->addOrderHistory($order_id, ORDER_STATUS_COMPLETE, "Status: Paid. Invoice No: $invoice_no. Method: Callback ", true, true);
                        }
                }

                $this->rdr($this->url->link('checkout/success'));
        }

        public function rdr($location)
        {
                if (!headers_sent()) {
                        header('Location: ' . $location);
                } else {
                        echo "If you are not redirected, please click <a href=" . '"' . $location . '"' . " target='_self'>Here</a><br />"
                                . "<script>location.href = '" . $location . "'</script>";
                }

                exit();
        }
        function guidv4($data = null)
        {
                // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
                $data = $data ?? random_bytes(16);
                assert(strlen($data) == 16);

                // Set version to 0100
                $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                // Set bits 6-7 to 10
                $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

                // Output the 36 character UUID.
                return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
}