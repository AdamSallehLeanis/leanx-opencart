<?php
class ModelExtensionPaymentLeanx extends Model
{

  public function install()
  {
    $this->db->query("
      CREATE TABLE `" . DB_PREFIX . "leanx_bill` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `invoice_no` varchar(255) NOT NULL,
        `order_id` varchar(255) NOT NULL,
        `paid` INT(1) DEFAULT 0 NOT NULL,
        UNIQUE (invoice_no),
        KEY `order_id` (`order_id`),
        PRIMARY KEY `id` (`id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
    ");
  }

  public function uninstall()
  {
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "leanx_bill`;");
  }
}