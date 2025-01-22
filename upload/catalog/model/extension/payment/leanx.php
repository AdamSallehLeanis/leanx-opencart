<?php

class ModelExtensionPaymentLeanx extends Model
{
  public function getMethod($address, $total)
  {
    $this->load->language('extension/payment/leanx');
    $status = true;

    $currencies = array('MYR');
    if (!in_array(strtoupper($this->session->data['currency']), $currencies)) {
      $status = false;
    }

    $method_data = array();

    if ($status) {
      $method_data = array(
        'code' => 'leanx',
        'title' => "LeanX Payment Gateaway",
        'terms' => '',
        'sort_order' => $this->config->get('payment_leanx_sort_order')
      );
    }

    return $method_data;
  }

  public function insertBill($order_id, $invoice_no)
  {
    $qry = $this->db->query("INSERT INTO `" . DB_PREFIX . "leanx_bill` (`order_id`, `invoice_no`) VALUES ('$order_id', '$invoice_no')");

    if ($qry) {
      return true;
    }

    $this->logger($qry);
    return false;
  }

  public function getBill($invoice_no)
  {
    $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "leanx_bill` WHERE `invoice_no` = '" . $invoice_no . "' LIMIT 1");

    if ($qry->num_rows) {
      return $qry->rows[0];
    }
    return false;
  }

  public function markBillPaid($order_id, $invoice_no)
  {
    $qry = $this->db->query("UPDATE `" . DB_PREFIX . "leanx_bill` SET `paid` = '1' WHERE `order_id` = '$order_id' AND `invoice_no` = '$invoice_no' AND `paid` = '0'");

    if ($qry) {
      return true;
    }
    return false;
  }

  public function logger($message)
  {
    $log = new Log('leanx.log');
    $backtrace = debug_backtrace();
    $log->write('Origin: ' . $backtrace[1]['class'] . '::' . $backtrace[1]['function']);
    $log->write(print_r($message, 1));
  }
}